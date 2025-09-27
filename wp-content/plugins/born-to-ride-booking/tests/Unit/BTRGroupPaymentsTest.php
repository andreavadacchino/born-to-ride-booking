<?php
/**
 * Unit Tests for BTR_Group_Payments
 *
 * @package BornToRideBooking\Tests\Unit
 */

namespace BornToRideBooking\Tests\Unit;

use BornToRideBooking\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test BTR_Group_Payments class
 */
class BTRGroupPaymentsTest extends TestCase {

    /**
     * Group payments instance
     */
    private $group_payments;

    /**
     * Mock database
     */
    private $wpdb_mock;

    /**
     * Setup test
     */
    protected function setUp(): void {
        parent::setUp();

        // Mock global wpdb
        $this->wpdb_mock = Mockery::mock('wpdb');
        $this->wpdb_mock->prefix = 'wp_';
        $this->wpdb_mock->shouldReceive('prepare')->andReturnUsing(function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
        });

        $GLOBALS['wpdb'] = $this->wpdb_mock;

        // Load class
        if (!class_exists('BTR_Group_Payments')) {
            require_once BTR_PLUGIN_ROOT . '/includes/class-btr-group-payments.php';
        }

        $this->group_payments = new \BTR_Group_Payments();
    }

    /**
     * Test create payment record
     */
    public function test_create_payment_record() {
        $payment_data = [
            'preventivo_id' => 123,
            'order_id' => 456,
            'payer_email' => 'test@example.com',
            'amount' => 100.50,
            'payment_status' => 'pending'
        ];

        $this->wpdb_mock->shouldReceive('insert')
            ->once()
            ->with(
                'wp_btr_group_payments',
                Mockery::on(function($data) {
                    return $data['preventivo_id'] == 123 &&
                           $data['payer_email'] == 'test@example.com' &&
                           $data['amount'] == 100.50;
                }),
                ['%d', '%d', '%s', '%f', '%s']
            )
            ->andReturn(1);

        $this->wpdb_mock->insert_id = 789;

        $result = $this->group_payments->create_payment($payment_data);

        $this->assertEquals(789, $result);
    }

    /**
     * Test get payments by preventivo
     */
    public function test_get_payments_by_preventivo() {
        $preventivo_id = 123;

        $mock_payments = [
            (object)[
                'payment_id' => 1,
                'preventivo_id' => 123,
                'payer_email' => 'user1@example.com',
                'amount' => 100.00,
                'payment_status' => 'completed'
            ],
            (object)[
                'payment_id' => 2,
                'preventivo_id' => 123,
                'payer_email' => 'user2@example.com',
                'amount' => 150.00,
                'payment_status' => 'pending'
            ]
        ];

        $this->wpdb_mock->shouldReceive('get_results')
            ->once()
            ->andReturn($mock_payments);

        $result = $this->group_payments->get_payments_by_preventivo($preventivo_id);

        $this->assertCount(2, $result);
        $this->assertEquals(250.00, array_sum(array_column($result, 'amount')));
    }

    /**
     * Test update payment status
     */
    public function test_update_payment_status() {
        $payment_id = 456;
        $new_status = 'completed';

        $this->wpdb_mock->shouldReceive('update')
            ->once()
            ->with(
                'wp_btr_group_payments',
                ['payment_status' => $new_status, 'payment_date' => Mockery::any()],
                ['payment_id' => $payment_id],
                ['%s', '%s'],
                ['%d']
            )
            ->andReturn(1);

        $result = $this->group_payments->update_payment_status($payment_id, $new_status);

        $this->assertTrue($result);
    }

    /**
     * Test calculate total collected
     */
    public function test_calculate_total_collected() {
        $preventivo_id = 123;

        $this->wpdb_mock->shouldReceive('get_var')
            ->once()
            ->andReturn('350.75');

        $result = $this->group_payments->get_total_collected($preventivo_id);

        $this->assertEquals(350.75, $result);
    }

    /**
     * Test validate payment data
     */
    public function test_validate_payment_data() {
        // Valid data
        $valid_data = [
            'preventivo_id' => 123,
            'payer_email' => 'test@example.com',
            'amount' => 100.00
        ];

        $is_valid = $this->callPrivateMethod(
            $this->group_payments,
            'validate_payment_data',
            [$valid_data]
        );

        $this->assertTrue($is_valid);

        // Invalid email
        $invalid_email = [
            'preventivo_id' => 123,
            'payer_email' => 'invalid-email',
            'amount' => 100.00
        ];

        $is_valid = $this->callPrivateMethod(
            $this->group_payments,
            'validate_payment_data',
            [$invalid_email]
        );

        $this->assertFalse($is_valid);

        // Negative amount
        $negative_amount = [
            'preventivo_id' => 123,
            'payer_email' => 'test@example.com',
            'amount' => -50.00
        ];

        $is_valid = $this->callPrivateMethod(
            $this->group_payments,
            'validate_payment_data',
            [$negative_amount]
        );

        $this->assertFalse($is_valid);
    }

    /**
     * Test generate payment token
     */
    public function test_generate_payment_token() {
        Functions\when('wp_generate_password')->justReturn('test_token_123456');
        Functions\when('wp_hash')->alias(function($data) {
            return 'hashed_' . $data;
        });

        $token = $this->callPrivateMethod(
            $this->group_payments,
            'generate_payment_token',
            [123, 'test@example.com']
        );

        $this->assertStringContainsString('hashed_', $token);
        $this->assertGreaterThan(20, strlen($token));
    }

    /**
     * Test send payment invitation
     */
    public function test_send_payment_invitation() {
        Functions\when('wp_mail')->justReturn(true);
        Functions\when('get_bloginfo')->justReturn('Test Site');
        Functions\when('home_url')->alias(function($path = '') {
            return 'http://example.com' . $path;
        });

        $result = $this->group_payments->send_payment_invitation(
            'test@example.com',
            123,
            100.00,
            'test_token'
        );

        $this->assertTrue($result);
    }

    /**
     * Test payment expiration
     */
    public function test_payment_expiration() {
        $created_at = '2024-01-01 12:00:00';
        $expiry_days = 7;

        // Test not expired
        $check_date = '2024-01-05 12:00:00';
        $is_expired = $this->callPrivateMethod(
            $this->group_payments,
            'is_payment_expired',
            [$created_at, $check_date, $expiry_days]
        );
        $this->assertFalse($is_expired);

        // Test expired
        $check_date = '2024-01-10 12:00:00';
        $is_expired = $this->callPrivateMethod(
            $this->group_payments,
            'is_payment_expired',
            [$created_at, $check_date, $expiry_days]
        );
        $this->assertTrue($is_expired);
    }

    /**
     * Test payment allocation
     */
    public function test_payment_allocation() {
        $total_amount = 1000.00;
        $num_participants = 4;

        // Equal split
        $allocation = $this->group_payments->calculate_payment_allocation(
            $total_amount,
            $num_participants,
            'equal'
        );

        $this->assertEquals(250.00, $allocation['per_person']);
        $this->assertEquals(1000.00, $allocation['total']);

        // Custom allocation
        $custom_amounts = [300, 300, 200, 200];
        $allocation = $this->group_payments->calculate_payment_allocation(
            $total_amount,
            $num_participants,
            'custom',
            $custom_amounts
        );

        $this->assertEquals($custom_amounts, $allocation['amounts']);
        $this->assertEquals(1000.00, array_sum($custom_amounts));
    }

    /**
     * Test refund processing
     */
    public function test_process_refund() {
        $payment_id = 789;

        $this->wpdb_mock->shouldReceive('update')
            ->once()
            ->with(
                'wp_btr_group_payments',
                ['payment_status' => 'refunded'],
                ['payment_id' => $payment_id],
                ['%s'],
                ['%d']
            )
            ->andReturn(1);

        Functions\when('do_action')->justReturn(null);

        $result = $this->group_payments->process_refund($payment_id);

        $this->assertTrue($result);
    }

    /**
     * Test payment reminder scheduling
     */
    public function test_schedule_payment_reminder() {
        Functions\when('wp_schedule_single_event')->justReturn(true);
        Functions\when('time')->justReturn(1704110400); // 2024-01-01 12:00:00

        $result = $this->group_payments->schedule_payment_reminder(
            123,
            'test@example.com',
            3 // days
        );

        $this->assertTrue($result);
    }
}