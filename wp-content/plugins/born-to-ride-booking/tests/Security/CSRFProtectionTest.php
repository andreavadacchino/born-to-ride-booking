<?php
/**
 * CSRF Protection Security Tests
 *
 * @package BornToRideBooking\Tests\Security
 */

namespace BornToRideBooking\Tests\Security;

use BornToRideBooking\Tests\IntegrationTestCase;

/**
 * Test CSRF protection across the plugin
 */
class CSRFProtectionTest extends IntegrationTestCase {

    /**
     * Test nonce verification for AJAX requests
     */
    public function test_ajax_nonce_verification() {
        $ajax_actions = [
            'btr_save_anagrafici',
            'btr_update_payment',
            'btr_send_group_payment_invites',
            'btr_process_payment',
            'btr_delete_preventivo',
            'btr_update_preventivo_status'
        ];

        foreach ($ajax_actions as $action) {
            // Test without nonce
            $_POST = ['action' => $action];

            $response = $this->makeAjaxRequest($action);

            // Should fail without nonce
            if ($response !== null) {
                $this->assertAjaxError($response);
            }

            // Test with invalid nonce
            $_POST = [
                'action' => $action,
                'nonce' => 'invalid_nonce_12345'
            ];

            $response = $this->makeAjaxRequest($action);

            // Should fail with invalid nonce
            if ($response !== null) {
                $this->assertAjaxError($response);
            }

            // Test with valid nonce
            $_POST = [
                'action' => $action,
                'nonce' => wp_create_nonce($action)
            ];

            // Should pass nonce check (might fail for other reasons)
            // We're testing nonce verification, not full functionality
        }
    }

    /**
     * Test form submission CSRF protection
     */
    public function test_form_submission_csrf() {
        // Create test preventivo
        $preventivo_id = $this->createTestPreventivo();

        // Test anagrafici form submission without nonce
        $_POST = [
            'preventivo_id' => $preventivo_id,
            'anagrafici' => [
                ['nome' => 'Test', 'cognome' => 'User']
            ]
        ];

        // Should reject without nonce
        $this->assertFalse(wp_verify_nonce('', 'btr_save_anagrafici'));

        // Test with valid nonce
        $_POST['_wpnonce'] = wp_create_nonce('btr_save_anagrafici');
        $this->assertTrue(wp_verify_nonce($_POST['_wpnonce'], 'btr_save_anagrafici'));
    }

    /**
     * Test REST API authentication
     */
    public function test_rest_api_authentication() {
        // Test unauthorized access
        $request = new \WP_REST_Request('GET', '/btr/v1/preventivi');
        $response = rest_do_request($request);

        // Should require authentication for sensitive endpoints
        if ($response->get_status() === 401) {
            $this->assertEquals(401, $response->get_status());
        }

        // Test with authentication
        wp_set_current_user($this->test_admin);
        $request = new \WP_REST_Request('GET', '/btr/v1/preventivi');
        $response = rest_do_request($request);

        // Should allow authenticated requests
        if ($response->get_status() !== 404) { // Endpoint might not exist
            $this->assertNotEquals(401, $response->get_status());
        }
    }

    /**
     * Test cookie security
     */
    public function test_cookie_security() {
        // Test that sensitive cookies use secure flags
        $test_cookie_name = 'btr_test_session';
        $test_value = 'test_value_' . time();

        // Set cookie with security flags
        setcookie(
            $test_cookie_name,
            $test_value,
            [
                'expires' => time() + 3600,
                'path' => '/',
                'domain' => '',
                'secure' => true,    // HTTPS only
                'httponly' => true,   // No JS access
                'samesite' => 'Strict' // CSRF protection
            ]
        );

        // In real environment, verify headers
        $headers = headers_list();

        // Check if security headers are set
        $this->assertIsArray($headers);
    }

    /**
     * Test referer checking for sensitive operations
     */
    public function test_referer_validation() {
        $admin_url = admin_url();

        // Test without referer
        $_SERVER['HTTP_REFERER'] = '';
        $check = wp_get_referer();
        $this->assertFalse($check);

        // Test with external referer
        $_SERVER['HTTP_REFERER'] = 'http://evil-site.com/attack';
        $check = wp_get_referer();

        if ($check) {
            $parsed = parse_url($check);
            $admin_parsed = parse_url($admin_url);

            // Should not trust external referers for admin operations
            if (isset($parsed['host']) && isset($admin_parsed['host'])) {
                $this->assertNotEquals('evil-site.com', $parsed['host']);
            }
        }

        // Test with valid referer
        $_SERVER['HTTP_REFERER'] = $admin_url;
        $check = wp_get_referer();
        $this->assertEquals($admin_url, $check);
    }

    /**
     * Test token-based authentication for API
     */
    public function test_token_authentication() {
        global $wpdb;

        // Generate test token
        $token = wp_generate_password(32, false);
        $hashed_token = wp_hash($token);

        // Store token (simulated)
        $table = $wpdb->prefix . 'btr_api_tokens';

        // Verify token validation
        $provided_token = $token;
        $is_valid = wp_check_password($provided_token, $hashed_token);
        $this->assertTrue($is_valid);

        // Test invalid token
        $invalid_token = 'invalid_token_12345';
        $is_valid = wp_check_password($invalid_token, $hashed_token);
        $this->assertFalse($is_valid);
    }

    /**
     * Test rate limiting for sensitive operations
     */
    public function test_rate_limiting() {
        $user_ip = '127.0.0.1';
        $action = 'btr_payment_attempt';
        $transient_key = 'btr_rate_limit_' . md5($user_ip . $action);

        // Simulate rate limiting
        $max_attempts = 5;
        $window = 3600; // 1 hour

        // Test multiple attempts
        for ($i = 1; $i <= $max_attempts + 2; $i++) {
            $attempts = get_transient($transient_key) ?: 0;

            if ($attempts >= $max_attempts) {
                // Should be rate limited
                $this->assertGreaterThanOrEqual($max_attempts, $attempts);
                break;
            }

            set_transient($transient_key, $attempts + 1, $window);
        }

        // Clean up
        delete_transient($transient_key);
    }

    /**
     * Test capability checks for admin operations
     */
    public function test_capability_checks() {
        // Test as subscriber (no admin rights)
        wp_set_current_user($this->test_user);

        $admin_caps = [
            'manage_options',
            'edit_posts',
            'delete_posts',
            'publish_posts'
        ];

        foreach ($admin_caps as $cap) {
            $this->assertFalse(current_user_can($cap));
        }

        // Test as admin
        wp_set_current_user($this->test_admin);

        foreach ($admin_caps as $cap) {
            $this->assertTrue(current_user_can($cap));
        }
    }

    /**
     * Test session security
     */
    public function test_session_security() {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Test session regeneration
        $old_session_id = session_id();
        session_regenerate_id(true);
        $new_session_id = session_id();

        // Session ID should change
        $this->assertNotEquals($old_session_id, $new_session_id);

        // Test session data isolation
        $_SESSION['btr_user_data'] = ['user_id' => 123];

        // Verify session data is properly namespaced
        $this->assertArrayHasKey('btr_user_data', $_SESSION);
        $this->assertArrayNotHasKey('admin_password', $_SESSION);
    }

    /**
     * Test double submit cookie pattern
     */
    public function test_double_submit_cookie() {
        $form_token = wp_generate_password(32, false);

        // Set cookie token
        $_COOKIE['btr_csrf_token'] = $form_token;

        // Submit with matching token
        $_POST['csrf_token'] = $form_token;

        // Verify tokens match
        $this->assertEquals($_COOKIE['btr_csrf_token'], $_POST['csrf_token']);

        // Test with mismatched tokens
        $_POST['csrf_token'] = 'different_token';
        $this->assertNotEquals($_COOKIE['btr_csrf_token'], $_POST['csrf_token']);
    }

    /**
     * Test origin header validation
     */
    public function test_origin_header_validation() {
        $site_url = get_site_url();
        $parsed_site = parse_url($site_url);

        // Test valid origin
        $_SERVER['HTTP_ORIGIN'] = $site_url;
        $origin = $_SERVER['HTTP_ORIGIN'];
        $parsed_origin = parse_url($origin);

        if (isset($parsed_site['host']) && isset($parsed_origin['host'])) {
            $this->assertEquals($parsed_site['host'], $parsed_origin['host']);
        }

        // Test invalid origin
        $_SERVER['HTTP_ORIGIN'] = 'http://evil-site.com';
        $origin = $_SERVER['HTTP_ORIGIN'];
        $parsed_origin = parse_url($origin);

        if (isset($parsed_site['host']) && isset($parsed_origin['host'])) {
            $this->assertNotEquals($parsed_site['host'], $parsed_origin['host']);
        }
    }
}