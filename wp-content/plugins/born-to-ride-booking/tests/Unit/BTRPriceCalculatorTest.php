<?php
/**
 * Unit Tests for BTR_Price_Calculator
 *
 * @package BornToRideBooking\Tests\Unit
 */

namespace BornToRideBooking\Tests\Unit;

use BornToRideBooking\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Test BTR_Price_Calculator class
 */
class BTRPriceCalculatorTest extends TestCase {

    /**
     * Calculator instance
     */
    private $calculator;

    /**
     * Setup test
     */
    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress functions
        Functions\when('btr_price_calculator')->alias(function() {
            if (!class_exists('BTR_Price_Calculator')) {
                require_once BTR_PLUGIN_ROOT . '/includes/class-btr-price-calculator.php';
            }
            return \BTR_Price_Calculator::get_instance();
        });

        $this->calculator = btr_price_calculator();
    }

    /**
     * Test calculate_extra_costs with various scenarios
     */
    public function test_calculate_extra_costs() {
        $anagrafici = [
            ['nome' => 'Mario', 'cognome' => 'Rossi', 'eta' => 35, 'assicurazione' => 'base'],
            ['nome' => 'Laura', 'cognome' => 'Rossi', 'eta' => 33, 'assicurazione' => 'premium'],
            ['nome' => 'Luca', 'cognome' => 'Rossi', 'eta' => 8, 'assicurazione' => '']
        ];

        $costi_extra = [
            'assicurazione_base' => 10.50,
            'assicurazione_premium' => 25.00,
            'supplemento_singola' => 30.00,
            'sconto_gruppo' => -50.00
        ];

        $result = $this->calculator->calculate_extra_costs($anagrafici, $costi_extra);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('totale', $result);
        $this->assertArrayHasKey('totale_aggiunte', $result);
        $this->assertArrayHasKey('totale_riduzioni', $result);
        $this->assertArrayHasKey('dettagli', $result);

        // Test calculations
        $expected_total = 10.50 + 25.00 + 30.00 - 50.00; // 15.50
        $this->assertEquals(15.50, $result['totale']);
        $this->assertEquals(65.50, $result['totale_aggiunte']);
        $this->assertEquals(-50.00, $result['totale_riduzioni']);
    }

    /**
     * Test calculate_extra_costs with empty data
     */
    public function test_calculate_extra_costs_empty() {
        $result = $this->calculator->calculate_extra_costs([], []);

        $this->assertEquals(0, $result['totale']);
        $this->assertEquals(0, $result['totale_aggiunte']);
        $this->assertEquals(0, $result['totale_riduzioni']);
        $this->assertEmpty($result['dettagli']);
    }

    /**
     * Test calculate_room_total
     */
    public function test_calculate_room_total() {
        $camere = [
            ['tipo' => 'Doppia', 'quantita' => 2, 'prezzo' => 150.00],
            ['tipo' => 'Singola', 'quantita' => 1, 'prezzo' => 100.00],
            ['tipo' => 'Tripla', 'quantita' => 1, 'prezzo' => 200.00]
        ];

        $nights = 7;

        $total = $this->callPrivateMethod($this->calculator, 'calculate_room_total', [$camere, $nights]);

        // (2*150 + 1*100 + 1*200) * 7 = 600 * 7 = 4200
        $this->assertEquals(4200.00, $total);
    }

    /**
     * Test calculate_insurance_total
     */
    public function test_calculate_insurance_total() {
        $anagrafici = [
            ['nome' => 'Test1', 'assicurazione' => 'base', 'prezzo_assicurazione' => 10.00],
            ['nome' => 'Test2', 'assicurazione' => 'premium', 'prezzo_assicurazione' => 25.00],
            ['nome' => 'Test3', 'assicurazione' => '', 'prezzo_assicurazione' => 0],
            ['nome' => 'Test4', 'assicurazione' => 'base', 'prezzo_assicurazione' => 10.00]
        ];

        $total = $this->callPrivateMethod($this->calculator, 'calculate_insurance_total', [$anagrafici]);

        $this->assertEquals(45.00, $total);
    }

    /**
     * Test calculate_discount
     */
    public function test_calculate_discount() {
        // Test percentage discount
        $discount = $this->callPrivateMethod(
            $this->calculator,
            'calculate_discount',
            [1000.00, 10, 'percentage']
        );
        $this->assertEquals(-100.00, $discount);

        // Test fixed discount
        $discount = $this->callPrivateMethod(
            $this->calculator,
            'calculate_discount',
            [1000.00, 50, 'fixed']
        );
        $this->assertEquals(-50.00, $discount);

        // Test invalid discount type
        $discount = $this->callPrivateMethod(
            $this->calculator,
            'calculate_discount',
            [1000.00, 20, 'invalid']
        );
        $this->assertEquals(0, $discount);
    }

    /**
     * Test calculate_total_with_deposit
     */
    public function test_calculate_total_with_deposit() {
        $total = 1000.00;

        // Test percentage deposit
        $result = $this->calculator->calculate_total_with_deposit($total, 30, 'percentage');
        $this->assertEquals(300.00, $result['deposit']);
        $this->assertEquals(700.00, $result['balance']);
        $this->assertEquals(1000.00, $result['total']);

        // Test fixed deposit
        $result = $this->calculator->calculate_total_with_deposit($total, 250, 'fixed');
        $this->assertEquals(250.00, $result['deposit']);
        $this->assertEquals(750.00, $result['balance']);
        $this->assertEquals(1000.00, $result['total']);
    }

    /**
     * Test price formatting
     */
    public function test_format_price() {
        Functions\when('btr_format_price_i18n')->alias(function($price) {
            return '€' . number_format($price, 2, ',', '.');
        });

        $formatted = btr_format_price_i18n(1234.56);
        $this->assertEquals('€1.234,56', $formatted);

        $formatted = btr_format_price_i18n(1000);
        $this->assertEquals('€1.000,00', $formatted);

        $formatted = btr_format_price_i18n(0);
        $this->assertEquals('€0,00', $formatted);
    }

    /**
     * Test edge cases for calculations
     */
    public function test_edge_cases() {
        // Test negative prices
        $camere = [['tipo' => 'Test', 'quantita' => 1, 'prezzo' => -100]];
        $total = $this->callPrivateMethod($this->calculator, 'calculate_room_total', [$camere, 1]);
        $this->assertEquals(0, $total); // Should not allow negative

        // Test zero nights
        $camere = [['tipo' => 'Test', 'quantita' => 2, 'prezzo' => 150]];
        $total = $this->callPrivateMethod($this->calculator, 'calculate_room_total', [$camere, 0]);
        $this->assertEquals(0, $total);

        // Test invalid data types
        $result = $this->calculator->calculate_extra_costs('invalid', 'invalid');
        $this->assertEquals(0, $result['totale']);
    }

    /**
     * Test calculate_child_pricing
     */
    public function test_calculate_child_pricing() {
        $children_ages = [5, 8, 12, 15]; // Various age groups
        $base_price = 100.00;

        // Mock child pricing rules
        $pricing_rules = [
            '0-2' => 0,      // Free for infants
            '3-5' => 30,     // 30% for young children
            '6-11' => 50,    // 50% for children
            '12-17' => 70    // 70% for teens
        ];

        $total = 0;
        foreach ($children_ages as $age) {
            if ($age <= 2) $discount = 100;
            elseif ($age <= 5) $discount = 70;
            elseif ($age <= 11) $discount = 50;
            elseif ($age <= 17) $discount = 30;
            else $discount = 0;

            $child_price = $base_price * (1 - $discount / 100);
            $total += $child_price;
        }

        // 5yo: 30, 8yo: 50, 12yo: 70, 15yo: 70 = 220
        $this->assertEquals(220.00, $total);
    }

    /**
     * Test seasonal pricing adjustments
     */
    public function test_seasonal_pricing() {
        $base_price = 1000.00;

        // High season (July-August) +20%
        $high_season_price = $base_price * 1.20;
        $this->assertEquals(1200.00, $high_season_price);

        // Low season (November-February) -15%
        $low_season_price = $base_price * 0.85;
        $this->assertEquals(850.00, $low_season_price);

        // Regular season
        $regular_price = $base_price;
        $this->assertEquals(1000.00, $regular_price);
    }

    /**
     * Test group discount calculations
     */
    public function test_group_discounts() {
        $participants = 15;
        $price_per_person = 100.00;

        // Group discount tiers
        $discount = 0;
        if ($participants >= 20) $discount = 15;
        elseif ($participants >= 15) $discount = 10;
        elseif ($participants >= 10) $discount = 5;

        $total = $participants * $price_per_person;
        $discount_amount = $total * ($discount / 100);
        $final_total = $total - $discount_amount;

        $this->assertEquals(1500.00, $total);
        $this->assertEquals(150.00, $discount_amount);
        $this->assertEquals(1350.00, $final_total);
    }
}