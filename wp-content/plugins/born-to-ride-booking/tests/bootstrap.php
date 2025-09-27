<?php
/**
 * PHPUnit bootstrap file for Born to Ride Booking plugin
 *
 * @package BornToRideBooking
 */

// Define test constants
define( 'BTR_TESTS_ROOT', dirname( __FILE__ ) );
define( 'BTR_PLUGIN_ROOT', dirname( BTR_TESTS_ROOT ) );
define( 'BTR_RUNNING_TESTS', true );

// Load composer autoloader
$composer_autoload = BTR_PLUGIN_ROOT . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
    require_once $composer_autoload;
}

// Determine where WordPress test suite is installed
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward compatibility with WP 5.9
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh?" . PHP_EOL;
    exit( 1 );
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // Load WooCommerce if available (for integration tests)
    $woocommerce = dirname( dirname( BTR_PLUGIN_ROOT ) ) . '/woocommerce/woocommerce.php';
    if ( file_exists( $woocommerce ) ) {
        require $woocommerce;
    }

    // Load our plugin
    require BTR_PLUGIN_ROOT . '/born-to-ride-booking.php';

    // Activate the plugin
    do_action( 'plugins_loaded' );
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Load WordPress test environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test base classes
require_once BTR_TESTS_ROOT . '/TestCase.php';
require_once BTR_TESTS_ROOT . '/IntegrationTestCase.php';

// Load PHPUnit Polyfills for WordPress compatibility
require_once BTR_PLUGIN_ROOT . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Activate plugin features for testing
add_filter( 'btr_enable_all_features', '__return_true' );

echo "Born to Ride Booking Test Suite Loaded\n";
echo "Plugin Directory: " . BTR_PLUGIN_ROOT . "\n";
echo "Test Directory: " . BTR_TESTS_ROOT . "\n";
echo "WordPress Test Directory: " . $_tests_dir . "\n\n";