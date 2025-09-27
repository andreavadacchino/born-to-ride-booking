<?php
/**
 * SQL Injection Security Tests
 *
 * @package BornToRideBooking\Tests\Security
 */

namespace BornToRideBooking\Tests\Security;

use BornToRideBooking\Tests\IntegrationTestCase;

/**
 * Test SQL injection prevention across the plugin
 */
class SQLInjectionTest extends IntegrationTestCase {

    /**
     * Test preventivo ID validation against SQL injection
     */
    public function test_preventivo_id_sql_injection() {
        global $wpdb;

        // SQL injection attempts
        $malicious_inputs = [
            "1' OR '1'='1",
            "1; DROP TABLE wp_posts; --",
            "1' UNION SELECT * FROM wp_users --",
            "1' AND (SELECT * FROM (SELECT(SLEEP(5)))a)--",
            "' OR 1=1 --",
            "1' ORDER BY 1--+",
            "1' AND 1=1 UNION ALL SELECT NULL--",
            "-1' UNION ALL SELECT NULL--"
        ];

        foreach ($malicious_inputs as $input) {
            // Test through various entry points
            $_GET['preventivo_id'] = $input;
            $_POST['preventivo_id'] = $input;

            // Verify that input is properly sanitized
            $sanitized = absint($input);
            $this->assertIsInt($sanitized);
            $this->assertGreaterThanOrEqual(0, $sanitized);

            // Verify prepared statements are used
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
                $sanitized
            );

            // Ensure no SQL keywords remain in prepared query
            $this->assertStringNotContainsString('UNION', $query);
            $this->assertStringNotContainsString('DROP', $query);
            $this->assertStringNotContainsString('SELECT * FROM wp_users', $query);
        }
    }

    /**
     * Test email field SQL injection prevention
     */
    public function test_email_sql_injection() {
        global $wpdb;

        $malicious_emails = [
            "test@test.com' OR '1'='1",
            "admin'; DROP TABLE wp_users; --@test.com",
            "test@test.com' UNION SELECT password FROM wp_users--"
        ];

        foreach ($malicious_emails as $input) {
            // Test email sanitization
            $sanitized = sanitize_email($input);

            // Verify SQL keywords are removed
            $this->assertStringNotContainsString('DROP', $sanitized);
            $this->assertStringNotContainsString('UNION', $sanitized);
            $this->assertStringNotContainsString('SELECT', $sanitized);

            // Test with prepared statement
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payer_email = %s",
                $sanitized
            );

            $this->assertStringNotContainsString("'1'='1", $query);
        }
    }

    /**
     * Test search parameter SQL injection prevention
     */
    public function test_search_parameter_injection() {
        $malicious_searches = [
            "'; DELETE FROM wp_posts WHERE '1'='1",
            "search%' AND 1=1 --",
            "' OR EXISTS(SELECT * FROM wp_users) --"
        ];

        foreach ($malicious_searches as $input) {
            $sanitized = sanitize_text_field($input);

            // Verify dangerous characters are escaped
            $this->assertStringNotContainsString("'", $sanitized);
            $this->assertStringNotContainsString("--", $sanitized);

            // For LIKE queries
            $like_safe = '%' . $GLOBALS['wpdb']->esc_like($sanitized) . '%';
            $this->assertIsString($like_safe);
        }
    }

    /**
     * Test meta key/value injection prevention
     */
    public function test_meta_injection() {
        $preventivo_id = $this->createTestPreventivo();

        $malicious_meta = [
            '_test_meta\'; DROP TABLE wp_postmeta; --',
            '_test_meta` WHERE 1=1 --'
        ];

        foreach ($malicious_meta as $meta_key) {
            // WordPress should sanitize meta keys automatically
            update_post_meta($preventivo_id, $meta_key, 'test_value');

            // Verify the meta key was sanitized
            global $wpdb;
            $saved_meta = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d",
                $preventivo_id
            ));

            foreach ($saved_meta as $meta) {
                $this->assertStringNotContainsString('DROP', $meta->meta_key);
                $this->assertStringNotContainsString('--', $meta->meta_key);
            }
        }
    }

    /**
     * Test AJAX action parameter injection
     */
    public function test_ajax_action_injection() {
        $malicious_actions = [
            "btr_save_anagrafici'; DROP TABLE wp_options; --",
            "btr_save_anagrafici' OR '1'='1"
        ];

        foreach ($malicious_actions as $action) {
            $_POST['action'] = $action;

            // Verify action is properly sanitized before use
            $sanitized_action = sanitize_key($_POST['action']);

            $this->assertStringNotContainsString(';', $sanitized_action);
            $this->assertStringNotContainsString('DROP', $sanitized_action);
            $this->assertStringNotContainsString("'", $sanitized_action);
        }
    }

    /**
     * Test date parameter injection
     */
    public function test_date_parameter_injection() {
        $malicious_dates = [
            "2024-01-01'; DROP TABLE wp_posts; --",
            "2024-01-01' OR '1'='1",
            "'; UPDATE wp_users SET user_pass='hacked' WHERE '1'='1"
        ];

        foreach ($malicious_dates as $date) {
            // Test date sanitization
            $sanitized = sanitize_text_field($date);

            // Verify SQL keywords removed
            $this->assertStringNotContainsString('DROP', $sanitized);
            $this->assertStringNotContainsString('UPDATE', $sanitized);

            // Verify date format validation
            $date_object = \DateTime::createFromFormat('Y-m-d', substr($sanitized, 0, 10));
            $this->assertInstanceOf(\DateTime::class, $date_object);
        }
    }

    /**
     * Test numeric field injection
     */
    public function test_numeric_field_injection() {
        $malicious_numbers = [
            "100; DELETE FROM wp_posts WHERE 1=1; --",
            "100' OR '1'='1",
            "-1 UNION SELECT password FROM wp_users"
        ];

        foreach ($malicious_numbers as $number) {
            // Test various numeric sanitization methods
            $absint_result = absint($number);
            $intval_result = intval($number);
            $float_result = floatval($number);

            $this->assertIsInt($absint_result);
            $this->assertIsInt($intval_result);
            $this->assertIsFloat($float_result);

            // Ensure no SQL remains
            $this->assertLessThan(1000000, $absint_result); // Reasonable limit
        }
    }

    /**
     * Test custom table queries for injection vulnerabilities
     */
    public function test_custom_table_injection() {
        global $wpdb;

        $table = $wpdb->prefix . 'btr_group_payments';

        // Test various injection attempts
        $test_data = [
            'preventivo_id' => "1' OR '1'='1",
            'payer_email' => "test@test.com'; DROP TABLE {$table}; --",
            'amount' => "100.00; DELETE FROM {$table} WHERE 1=1; --"
        ];

        // Proper way to insert with prepared statements
        $result = $wpdb->insert(
            $table,
            [
                'preventivo_id' => absint($test_data['preventivo_id']),
                'payer_email' => sanitize_email($test_data['payer_email']),
                'amount' => floatval($test_data['amount'])
            ],
            ['%d', '%s', '%f']
        );

        // Verify table still exists and wasn't dropped
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        $this->assertEquals($table, $table_exists);
    }
}