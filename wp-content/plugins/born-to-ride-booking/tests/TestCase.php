<?php
/**
 * Base Test Case for Born to Ride Booking plugin
 *
 * @package BornToRideBooking\Tests
 */

namespace BornToRideBooking\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Mockery;

/**
 * Base test case for unit tests
 */
abstract class TestCase extends PHPUnitTestCase {

    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions commonly used
        Functions\when('__')->returnArg();
        Functions\when('_e')->alias(function($text) { echo $text; });
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('absint')->alias(function($val) { return abs(intval($val)); });
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);

        // Setup default filters/actions
        Filters\expectAdded('init')->zeroOrMoreTimes();
        Actions\expectAdded('init')->zeroOrMoreTimes();
    }

    /**
     * Teardown test environment
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to create a mock preventivo post
     */
    protected function createMockPreventivo($id = 123, $meta = []) {
        $default_meta = [
            '_prezzo_totale' => 1000,
            '_numero_adulti' => 2,
            '_numero_bambini' => 1,
            '_numero_neonati' => 0,
            '_data_partenza' => '2024-06-01',
            '_data_ritorno' => '2024-06-08',
            '_pacchetto' => 456,
            '_anagrafici_preventivo' => serialize([]),
            '_costi_extra_durata' => serialize([]),
            '_camere_selezionate' => serialize([])
        ];

        $meta = array_merge($default_meta, $meta);

        Functions\when('get_post')->justReturn((object)[
            'ID' => $id,
            'post_type' => 'preventivi',
            'post_status' => 'publish',
            'post_title' => 'Preventivo #' . $id
        ]);

        foreach ($meta as $key => $value) {
            Functions\when('get_post_meta')->alias(function($post_id, $meta_key, $single) use ($id, $key, $value) {
                if ($post_id == $id && $meta_key == $key) {
                    return $single ? $value : [$value];
                }
                return $single ? '' : [];
            });
        }

        return $id;
    }

    /**
     * Helper to assert WordPress hook was added
     */
    protected function assertActionAdded($hook, $callback = null) {
        if ($callback) {
            Actions\expectAdded($hook)
                ->once()
                ->with(\Mockery::type('callable'), \Mockery::any(), \Mockery::any());
        } else {
            Actions\expectAdded($hook)->once();
        }
    }

    /**
     * Helper to assert WordPress filter was added
     */
    protected function assertFilterAdded($hook, $callback = null) {
        if ($callback) {
            Filters\expectAdded($hook)
                ->once()
                ->with(\Mockery::type('callable'), \Mockery::any(), \Mockery::any());
        } else {
            Filters\expectAdded($hook)->once();
        }
    }

    /**
     * Get private/protected property value
     */
    protected function getPrivateProperty($object, $property) {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set private/protected property value
     */
    protected function setPrivateProperty($object, $property, $value) {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call private/protected method
     */
    protected function callPrivateMethod($object, $method, array $args = []) {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}