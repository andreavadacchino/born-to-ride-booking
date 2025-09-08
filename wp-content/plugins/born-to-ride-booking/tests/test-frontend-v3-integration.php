<?php
/**
 * Test Frontend v3.0 Integration
 * 
 * Script di test per verificare l'integrazione e compatibilità del nuovo sistema frontend
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BTR Frontend v3.0 Integration Test Suite
 */
class BTR_Frontend_V3_Integration_Test {
    
    /**
     * Test results
     */
    private $test_results = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->run_all_tests();
        $this->display_results();
    }
    
    /**
     * Run all integration tests
     */
    private function run_all_tests() {
        echo "<h2>🧪 BTR Frontend v3.0 Integration Test Suite</h2>\n";
        
        // File existence tests
        $this->test_file_structure();
        
        // Feature flags tests
        $this->test_feature_flags();
        
        // AJAX endpoints tests
        $this->test_ajax_endpoints();
        
        // State Manager integration
        $this->test_state_manager_integration();
        
        // Performance tests
        $this->test_performance_metrics();
        
        // Legacy compatibility tests
        $this->test_legacy_compatibility();
        
        // Backend integration tests
        $this->test_backend_integration();
    }
    
    /**
     * Test file structure
     */
    private function test_file_structure() {
        echo "<h3>📁 File Structure Tests</h3>\n";
        
        $required_files = [
            'assets/js/btr-booking-app.js' => 'Entry Point',
            'assets/js/btr-state-manager.js' => 'State Manager',
            'assets/js/modules/btr-ajax-client.js' => 'AJAX Client',
            'assets/js/modules/btr-calculator-v3.js' => 'Calculator v3',
            'assets/js/modules/btr-form-handler.js' => 'Form Handler',
            'assets/js/modules/btr-validation.js' => 'Validation Module',
            'assets/js/modules/btr-ui-components.js' => 'UI Components',
            'includes/class-btr-frontend-v3-integration.php' => 'Backend Integration',
            'assets/js/frontend-scripts-legacy.js' => 'Legacy Backup'
        ];
        
        foreach ($required_files as $file => $description) {
            $full_path = BTR_PLUGIN_DIR . $file;
            $exists = file_exists($full_path);
            
            $this->add_test_result(
                "File: {$description}",
                $exists,
                $exists ? "✅ {$file}" : "❌ Missing: {$file}"
            );
        }
        
        // Check modules directory
        $modules_dir = BTR_PLUGIN_DIR . 'assets/js/modules/';
        $modules_exists = is_dir($modules_dir);
        
        $this->add_test_result(
            'Modules Directory',
            $modules_exists,
            $modules_exists ? '✅ Modules directory exists' : '❌ Modules directory missing'
        );
    }
    
    /**
     * Test feature flags
     */
    private function test_feature_flags() {
        echo "<h3>🚩 Feature Flags Tests</h3>\n";
        
        // Check if integration class is loaded
        $integration_loaded = class_exists('BTR_Frontend_V3_Integration');
        $this->add_test_result(
            'Integration Class',
            $integration_loaded,
            $integration_loaded ? '✅ BTR_Frontend_V3_Integration loaded' : '❌ Integration class not found'
        );
        
        // Test default feature flags
        $default_flags = [
            'v3_enabled' => false, // Should be disabled by default
            'legacy_fallback' => true, // Should be enabled by default
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        ];
        
        foreach ($default_flags as $flag => $expected) {
            $actual = get_option('btr_' . $flag, $expected);
            $matches = ($actual == $expected);
            
            $this->add_test_result(
                "Flag: {$flag}",
                $matches,
                $matches ? "✅ {$flag}: " . ($actual ? 'true' : 'false') : "⚠️ {$flag}: expected " . ($expected ? 'true' : 'false') . ", got " . ($actual ? 'true' : 'false')
            );
        }
    }
    
    /**
     * Test AJAX endpoints
     */
    private function test_ajax_endpoints() {
        echo "<h3>🌐 AJAX Endpoints Tests</h3>\n";
        
        $endpoints = [
            'btr_v3_calculate',
            'btr_v3_save_state', 
            'btr_v3_validate',
            'btr_track_performance',
            'btr_report_error'
        ];
        
        foreach ($endpoints as $endpoint) {
            $action_exists = has_action("wp_ajax_{$endpoint}");
            $nopriv_exists = has_action("wp_ajax_nopriv_{$endpoint}");
            
            $this->add_test_result(
                "AJAX: {$endpoint}",
                $action_exists && $nopriv_exists,
                ($action_exists && $nopriv_exists) ? "✅ {$endpoint} registered" : "❌ {$endpoint} missing"
            );
        }
    }
    
    /**
     * Test State Manager integration
     */
    private function test_state_manager_integration() {
        echo "<h3>🔄 State Manager Integration Tests</h3>\n";
        
        // Check if State Manager file exists and is readable
        $state_manager_file = BTR_PLUGIN_DIR . 'assets/js/btr-state-manager.js';
        $state_manager_exists = file_exists($state_manager_file);
        $state_manager_readable = is_readable($state_manager_file);
        
        $this->add_test_result(
            'State Manager File',
            $state_manager_exists && $state_manager_readable,
            ($state_manager_exists && $state_manager_readable) ? '✅ State Manager accessible' : '❌ State Manager file issues'
        );
        
        // Check State Manager content for key functions
        if ($state_manager_exists) {
            $content = file_get_contents($state_manager_file);
            
            $required_patterns = [
                'BTRStateManager' => 'Main class definition',
                'updateState' => 'State update method',
                'getState' => 'State getter method',
                'validate' => 'Validation method',
                'saveToSession' => 'Session persistence'
            ];
            
            foreach ($required_patterns as $pattern => $description) {
                $found = strpos($content, $pattern) !== false;
                
                $this->add_test_result(
                    "State Manager: {$description}",
                    $found,
                    $found ? "✅ {$pattern} found" : "❌ {$pattern} missing"
                );
            }
        }
    }
    
    /**
     * Test performance metrics
     */
    private function test_performance_metrics() {
        echo "<h3>⚡ Performance Tests</h3>\n";
        
        // File size tests
        $files_to_check = [
            'assets/js/btr-booking-app.js' => ['max_size' => 50000, 'description' => 'Entry Point'],
            'assets/js/modules/btr-ajax-client.js' => ['max_size' => 40000, 'description' => 'AJAX Client'],
            'assets/js/modules/btr-calculator-v3.js' => ['max_size' => 60000, 'description' => 'Calculator'],
            'assets/js/modules/btr-form-handler.js' => ['max_size' => 70000, 'description' => 'Form Handler']
        ];
        
        $total_v3_size = 0;
        
        foreach ($files_to_check as $file => $config) {
            $full_path = BTR_PLUGIN_DIR . $file;
            if (file_exists($full_path)) {
                $size = filesize($full_path);
                $total_v3_size += $size;
                $size_ok = $size <= $config['max_size'];
                
                $this->add_test_result(
                    "Size: {$config['description']}",
                    $size_ok,
                    $size_ok ? "✅ " . number_format($size / 1024, 1) . "KB" : "⚠️ " . number_format($size / 1024, 1) . "KB (over " . number_format($config['max_size'] / 1024, 1) . "KB limit)"
                );
            }
        }
        
        // Compare with legacy file size
        $legacy_file = BTR_PLUGIN_DIR . 'assets/js/frontend-scripts-legacy.js';
        if (file_exists($legacy_file)) {
            $legacy_size = filesize($legacy_file);
            $size_reduction = (($legacy_size - $total_v3_size) / $legacy_size) * 100;
            
            $this->add_test_result(
                'Total Size Comparison',
                $size_reduction > 0,
                $size_reduction > 0 ? 
                    "✅ " . number_format($size_reduction, 1) . "% reduction (" . number_format($total_v3_size / 1024, 1) . "KB vs " . number_format($legacy_size / 1024, 1) . "KB)" :
                    "⚠️ No size improvement"
            );
        }
    }
    
    /**
     * Test legacy compatibility
     */
    private function test_legacy_compatibility() {
        echo "<h3>🔄 Legacy Compatibility Tests</h3>\n";
        
        // Check if legacy backup exists
        $legacy_backup = BTR_PLUGIN_DIR . 'assets/js/frontend-scripts-legacy.js';
        $legacy_exists = file_exists($legacy_backup);
        
        $this->add_test_result(
            'Legacy Backup',
            $legacy_exists,
            $legacy_exists ? '✅ Legacy backup created' : '❌ Legacy backup missing'
        );
        
        // Check if legacy functions would still work
        if ($legacy_exists) {
            $legacy_content = file_get_contents($legacy_backup);
            
            $legacy_functions = [
                'btrBookingState' => 'Legacy state object',
                'recalculateTotal' => 'Legacy calculation method',
                'btrFormatPrice' => 'Legacy price formatting',
                'btrParsePrice' => 'Legacy price parsing'
            ];
            
            foreach ($legacy_functions as $func => $description) {
                $found = strpos($legacy_content, $func) !== false;
                
                $this->add_test_result(
                    "Legacy: {$description}",
                    $found,
                    $found ? "✅ {$func} preserved" : "⚠️ {$func} might be missing"
                );
            }
        }
    }
    
    /**
     * Test backend integration
     */
    private function test_backend_integration() {
        echo "<h3>🔧 Backend Integration Tests</h3>\n";
        
        // Check required PHP classes
        $required_classes = [
            'BTR_Frontend_V3_Integration' => 'Frontend integration class',
            'BTR_Preventivi' => 'Legacy calculation class',
            'BTR_Unified_Calculator' => 'New unified calculator (optional)'
        ];
        
        foreach ($required_classes as $class => $description) {
            $exists = class_exists($class);
            $required = $class !== 'BTR_Unified_Calculator'; // Unified calculator is optional
            
            if ($required) {
                $this->add_test_result(
                    "Backend: {$description}",
                    $exists,
                    $exists ? "✅ {$class} loaded" : "❌ {$class} missing"
                );
            } else {
                $this->add_test_result(
                    "Backend: {$description}",
                    true, // Always pass for optional classes
                    $exists ? "✅ {$class} available" : "ℹ️ {$class} not available (optional)"
                );
            }
        }
        
        // Check WordPress hooks
        $required_hooks = [
            'wp_enqueue_scripts' => 'Script enqueuing',
            'admin_menu' => 'Admin interface',
            'admin_init' => 'Settings registration'
        ];
        
        foreach ($required_hooks as $hook => $description) {
            $has_hook = has_action($hook);
            
            $this->add_test_result(
                "Hook: {$description}",
                $has_hook,
                $has_hook ? "✅ {$hook} registered" : "⚠️ {$hook} not found"
            );
        }
    }
    
    /**
     * Add test result
     */
    private function add_test_result($test_name, $passed, $message) {
        $this->test_results[] = [
            'name' => $test_name,
            'passed' => $passed,
            'message' => $message
        ];
        
        echo "<div style='margin: 5px 0; font-family: monospace;'>{$message}</div>\n";
    }
    
    /**
     * Display test results summary
     */
    private function display_results() {
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($result) { return $result['passed']; }));
        $failed_tests = $total_tests - $passed_tests;
        
        $pass_rate = $total_tests > 0 ? ($passed_tests / $total_tests) * 100 : 0;
        
        echo "<h2>📊 Test Results Summary</h2>\n";
        echo "<div style='background: #f0f0f0; padding: 20px; border-radius: 5px; font-family: monospace;'>\n";
        echo "<strong>Total Tests:</strong> {$total_tests}<br>\n";
        echo "<strong>Passed:</strong> <span style='color: green;'>{$passed_tests}</span><br>\n";
        echo "<strong>Failed:</strong> <span style='color: red;'>{$failed_tests}</span><br>\n";
        echo "<strong>Pass Rate:</strong> " . number_format($pass_rate, 1) . "%<br>\n";
        
        if ($pass_rate >= 90) {
            echo "<br><span style='color: green; font-weight: bold;'>🎉 Excellent! System ready for deployment.</span>\n";
        } elseif ($pass_rate >= 75) {
            echo "<br><span style='color: orange; font-weight: bold;'>⚠️ Good but needs attention. Review failed tests.</span>\n";
        } else {
            echo "<br><span style='color: red; font-weight: bold;'>❌ Critical issues found. System not ready.</span>\n";
        }
        
        echo "</div>\n";
        
        // Detailed failure analysis
        if ($failed_tests > 0) {
            echo "<h3>❌ Failed Tests Details</h3>\n";
            foreach ($this->test_results as $result) {
                if (!$result['passed']) {
                    echo "<div style='color: red; margin: 5px 0;'>• {$result['name']}: {$result['message']}</div>\n";
                }
            }
        }
        
        // Recommendations
        $this->display_recommendations($pass_rate);
    }
    
    /**
     * Display recommendations based on test results
     */
    private function display_recommendations($pass_rate) {
        echo "<h3>💡 Recommendations</h3>\n";
        
        if ($pass_rate >= 90) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
            echo "<strong>✅ Ready for Production:</strong><br>\n";
            echo "• Enable v3 system with feature flag: <code>btr_v3_enabled = true</code><br>\n";
            echo "• Start A/B testing with 5% of users<br>\n";
            echo "• Monitor performance metrics closely<br>\n";
            echo "• Keep legacy fallback enabled during initial rollout<br>\n";
            echo "</div>\n";
        } elseif ($pass_rate >= 75) {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>\n";
            echo "<strong>⚠️ Needs Attention:</strong><br>\n";
            echo "• Fix failed tests before deployment<br>\n";
            echo "• Test on staging environment first<br>\n";
            echo "• Ensure all required files are present<br>\n";
            echo "• Verify backend integration works properly<br>\n";
            echo "</div>\n";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
            echo "<strong>❌ Critical Issues:</strong><br>\n";
            echo "• DO NOT deploy to production<br>\n";
            echo "• Review file structure and missing components<br>\n";
            echo "• Check backend PHP class integration<br>\n";
            echo "• Verify all module files are properly uploaded<br>\n";
            echo "• Re-run tests after fixes<br>\n";
            echo "</div>\n";
        }
        
        echo "<h4>🔧 Quick Fixes</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>Missing Files:</strong> Ensure all module files are uploaded to correct directories</li>\n";
        echo "<li><strong>AJAX Issues:</strong> Check if BTR_Frontend_V3_Integration class is properly loaded</li>\n";
        echo "<li><strong>Performance:</strong> Enable gzip compression and browser caching</li>\n";
        echo "<li><strong>Compatibility:</strong> Test with different themes and plugins</li>\n";
        echo "</ul>\n";
    }
}

// Run tests if accessed directly or via admin
if (current_user_can('manage_options') && (defined('WP_CLI') || isset($_GET['btr_test_v3']))) {
    echo "<!DOCTYPE html><html><head><title>BTR Frontend v3.0 Integration Test</title></head><body>";
    echo "<h1>🧪 BTR Frontend v3.0 Integration Test</h1>";
    echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>";
    
    new BTR_Frontend_V3_Integration_Test();
    
    echo "<hr><p><small>Test URL: <code>" . admin_url('admin.php?page=btr-settings&btr_test_v3=1') . "</code></small></p>";
    echo "</body></html>";
}