<?php
/**
 * Integration Test Case for Born to Ride Booking plugin
 *
 * @package BornToRideBooking\Tests
 */

namespace BornToRideBooking\Tests;

use WP_UnitTestCase;

/**
 * Base test case for WordPress integration tests
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {

    /**
     * Plugin instance
     */
    protected $plugin;

    /**
     * Test user ID
     */
    protected $test_user;

    /**
     * Test admin ID
     */
    protected $test_admin;

    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Create test users
        $this->test_user = $this->factory->user->create([
            'role' => 'subscriber',
            'user_login' => 'test_user',
            'user_email' => 'test@example.com'
        ]);

        $this->test_admin = $this->factory->user->create([
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'user_email' => 'admin@example.com'
        ]);

        // Initialize plugin
        $this->plugin = born_to_ride_booking();

        // Setup custom tables
        $this->setupCustomTables();

        // Clear any existing transients
        $this->clearTransients();
    }

    /**
     * Teardown test environment
     */
    public function tearDown(): void {
        // Clean up test data
        $this->cleanupTestData();

        // Reset current user
        wp_set_current_user(0);

        parent::tearDown();
    }

    /**
     * Setup custom database tables
     */
    protected function setupCustomTables() {
        global $wpdb;

        // Group payments table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}btr_group_payments (
            payment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            payer_email varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_status varchar(50) DEFAULT 'pending',
            payment_date datetime DEFAULT NULL,
            payment_token varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (payment_id),
            KEY preventivo_id (preventivo_id),
            KEY order_id (order_id),
            KEY payer_email (payer_email),
            KEY payment_status (payment_status)
        )";
        $wpdb->query($sql);
    }

    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        global $wpdb;

        // Clean custom tables
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}btr_group_payments");

        // Clean test posts
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type IN ('preventivi', 'prenotazioni', 'pacchetti')");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");

        // Clean test orders
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'shop_order'");

        // Clear transients
        $this->clearTransients();
    }

    /**
     * Clear plugin transients
     */
    protected function clearTransients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_btr_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_btr_%'");
    }

    /**
     * Create test preventivo
     */
    protected function createTestPreventivo($args = []) {
        $defaults = [
            'post_type' => 'preventivi',
            'post_status' => 'publish',
            'post_title' => 'Test Preventivo',
            'meta_input' => [
                '_prezzo_totale' => 1500.00,
                '_numero_adulti' => 2,
                '_numero_bambini' => 1,
                '_numero_neonati' => 0,
                '_data_partenza' => '2024-07-01',
                '_data_ritorno' => '2024-07-08',
                '_pacchetto' => $this->createTestPacchetto(),
                '_anagrafici_preventivo' => serialize([
                    ['nome' => 'Mario', 'cognome' => 'Rossi', 'eta' => 35],
                    ['nome' => 'Laura', 'cognome' => 'Rossi', 'eta' => 33],
                    ['nome' => 'Luca', 'cognome' => 'Rossi', 'eta' => 8]
                ]),
                '_camere_selezionate' => serialize([
                    ['tipo' => 'Doppia', 'quantita' => 1, 'prezzo' => 150]
                ])
            ]
        ];

        $args = wp_parse_args($args, $defaults);
        return wp_insert_post($args);
    }

    /**
     * Create test pacchetto
     */
    protected function createTestPacchetto($args = []) {
        $defaults = [
            'post_type' => 'pacchetti',
            'post_status' => 'publish',
            'post_title' => 'Test Pacchetto',
            'meta_input' => [
                '_prezzo_base' => 1000.00,
                '_durata_viaggio' => 7,
                '_disponibilita' => 10
            ]
        ];

        $args = wp_parse_args($args, $defaults);
        return wp_insert_post($args);
    }

    /**
     * Create test WooCommerce order
     */
    protected function createTestOrder($preventivo_id = null) {
        if (!class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $order = wc_create_order([
            'status' => 'pending',
            'customer_id' => $this->test_user
        ]);

        if ($preventivo_id) {
            $order->update_meta_data('_preventivo_id', $preventivo_id);
            $order->save();
        }

        return $order->get_id();
    }

    /**
     * Assert AJAX response
     */
    protected function assertAjaxSuccess($response) {
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
    }

    /**
     * Assert AJAX error
     */
    protected function assertAjaxError($response, $expected_message = null) {
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertFalse($response['success']);

        if ($expected_message) {
            $this->assertArrayHasKey('data', $response);
            $this->assertStringContainsString($expected_message, $response['data']);
        }
    }

    /**
     * Make AJAX request
     */
    protected function makeAjaxRequest($action, $data = []) {
        $_POST = array_merge([
            'action' => $action,
            'nonce' => wp_create_nonce($action)
        ], $data);

        ob_start();
        do_action('wp_ajax_' . $action);
        $output = ob_get_clean();

        return json_decode($output, true);
    }
}