<?php
/**
 * WordPress Hooks Integration Tests
 *
 * @package BornToRideBooking\Tests\Integration
 */

namespace BornToRideBooking\Tests\Integration;

use BornToRideBooking\Tests\IntegrationTestCase;

/**
 * Test WordPress hooks and filters integration
 */
class WordPressHooksTest extends IntegrationTestCase {

    /**
     * Test plugin activation hooks
     */
    public function test_plugin_activation_hooks() {
        // Test that activation creates necessary database tables
        global $wpdb;

        $table_name = $wpdb->prefix . 'btr_group_payments';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        $this->assertTrue($table_exists, 'Group payments table should exist');

        // Test default options are set
        $this->assertNotFalse(get_option('btr_plugin_version'));
        $this->assertNotFalse(get_option('btr_db_version'));
    }

    /**
     * Test init hooks are registered
     */
    public function test_init_hooks_registered() {
        // Check if essential hooks are registered
        $this->assertNotFalse(has_action('init'));
        $this->assertNotFalse(has_action('wp_enqueue_scripts'));
        $this->assertNotFalse(has_action('admin_enqueue_scripts'));
        $this->assertNotFalse(has_action('wp_ajax_btr_save_anagrafici'));
        $this->assertNotFalse(has_action('wp_ajax_nopriv_btr_save_anagrafici'));
    }

    /**
     * Test custom post types registration
     */
    public function test_custom_post_types_registered() {
        // Check post types exist
        $this->assertTrue(post_type_exists('preventivi'));
        $this->assertTrue(post_type_exists('prenotazioni'));
        $this->assertTrue(post_type_exists('pacchetti'));

        // Check post type capabilities
        $preventivi_obj = get_post_type_object('preventivi');
        $this->assertNotNull($preventivi_obj);
        $this->assertTrue($preventivi_obj->public);
    }

    /**
     * Test AJAX actions registration
     */
    public function test_ajax_actions_registered() {
        $ajax_actions = [
            'btr_save_anagrafici',
            'btr_update_payment',
            'btr_send_group_payment_invites',
            'btr_process_payment',
            'btr_get_preventivo_data',
            'btr_calculate_price'
        ];

        foreach ($ajax_actions as $action) {
            $this->assertNotFalse(
                has_action('wp_ajax_' . $action),
                "AJAX action {$action} should be registered"
            );
        }
    }

    /**
     * Test shortcodes registration
     */
    public function test_shortcodes_registered() {
        $shortcodes = [
            'btr_booking_form',
            'btr_anagrafici_form',
            'btr_payment_selection',
            'btr_preventivo_summary',
            'btr_organizer_dashboard'
        ];

        foreach ($shortcodes as $shortcode) {
            $this->assertTrue(
                shortcode_exists($shortcode),
                "Shortcode [{$shortcode}] should be registered"
            );
        }
    }

    /**
     * Test WooCommerce integration hooks
     */
    public function test_woocommerce_integration() {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        // Test checkout hooks
        $this->assertNotFalse(has_action('woocommerce_checkout_create_order'));
        $this->assertNotFalse(has_action('woocommerce_thankyou'));
        $this->assertNotFalse(has_filter('woocommerce_cart_calculate_fees'));
        $this->assertNotFalse(has_filter('woocommerce_checkout_fields'));
    }

    /**
     * Test admin menu registration
     */
    public function test_admin_menu_registered() {
        global $menu, $submenu;

        // Login as admin
        wp_set_current_user($this->test_admin);

        // Trigger admin menu
        do_action('admin_menu');

        // Check main menu exists
        $menu_exists = false;
        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && strpos($item[2], 'btr-') !== false) {
                    $menu_exists = true;
                    break;
                }
            }
        }

        $this->assertTrue($menu_exists, 'BTR admin menu should exist');
    }

    /**
     * Test REST API endpoints registration
     */
    public function test_rest_api_endpoints() {
        // Trigger REST API init
        do_action('rest_api_init');

        $server = rest_get_server();
        $routes = $server->get_routes();

        // Check for BTR endpoints
        $btr_routes = array_filter(array_keys($routes), function($route) {
            return strpos($route, '/btr/') !== false;
        });

        $this->assertNotEmpty($btr_routes, 'BTR REST API routes should be registered');
    }

    /**
     * Test cron events registration
     */
    public function test_cron_events_registered() {
        // Check for BTR cron events
        $cron = _get_cron_array();
        $btr_events = [];

        foreach ($cron as $timestamp => $events) {
            foreach ($events as $event_name => $event_data) {
                if (strpos($event_name, 'btr_') === 0) {
                    $btr_events[] = $event_name;
                }
            }
        }

        $expected_events = [
            'btr_daily_cleanup',
            'btr_payment_reminder',
            'btr_expired_quotes_cleanup'
        ];

        foreach ($expected_events as $event) {
            $this->assertContains($event, $btr_events, "Cron event {$event} should be scheduled");
        }
    }

    /**
     * Test rewrite rules
     */
    public function test_rewrite_rules() {
        global $wp_rewrite;

        // Flush rewrite rules
        flush_rewrite_rules();

        $rules = $wp_rewrite->wp_rewrite_rules();

        // Check for custom rewrite rules
        $btr_rules = array_filter(array_keys($rules), function($rule) {
            return strpos($rule, 'payment-dashboard') !== false ||
                   strpos($rule, 'organizer-panel') !== false;
        });

        $this->assertNotEmpty($btr_rules, 'BTR rewrite rules should exist');
    }

    /**
     * Test filter priorities
     */
    public function test_filter_priorities() {
        global $wp_filter;

        // Test that critical filters have correct priority
        if (isset($wp_filter['init'])) {
            $init_callbacks = $wp_filter['init']->callbacks;

            // Check that database installer runs early
            $has_early_db = false;
            foreach ([0, 1, 2, 3, 4, 5] as $priority) {
                if (isset($init_callbacks[$priority])) {
                    foreach ($init_callbacks[$priority] as $callback) {
                        if (is_array($callback['function']) &&
                            strpos(get_class($callback['function'][0]), 'Database') !== false) {
                            $has_early_db = true;
                            break 2;
                        }
                    }
                }
            }

            $this->assertTrue($has_early_db, 'Database initialization should run early');
        }
    }

    /**
     * Test script and style registration
     */
    public function test_assets_registration() {
        // Trigger enqueue scripts
        do_action('wp_enqueue_scripts');

        // Check frontend scripts
        $this->assertTrue(wp_script_is('btr-frontend', 'registered'));
        $this->assertTrue(wp_script_is('btr-anagrafici', 'registered'));

        // Check frontend styles
        $this->assertTrue(wp_style_is('btr-frontend', 'registered'));
        $this->assertTrue(wp_style_is('btr-checkout', 'registered'));

        // Admin assets
        wp_set_current_user($this->test_admin);
        set_current_screen('edit-preventivi');
        do_action('admin_enqueue_scripts');

        $this->assertTrue(wp_script_is('btr-admin', 'registered'));
        $this->assertTrue(wp_style_is('btr-admin', 'registered'));
    }

    /**
     * Test capability registration
     */
    public function test_custom_capabilities() {
        $admin_role = get_role('administrator');

        $custom_caps = [
            'manage_btr_bookings',
            'edit_preventivi',
            'delete_preventivi',
            'publish_preventivi',
            'view_btr_reports'
        ];

        foreach ($custom_caps as $cap) {
            $this->assertTrue(
                $admin_role->has_cap($cap),
                "Administrator should have {$cap} capability"
            );
        }
    }
}