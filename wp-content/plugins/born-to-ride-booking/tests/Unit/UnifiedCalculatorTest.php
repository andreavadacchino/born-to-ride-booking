<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UnifiedCalculatorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Reset in-memory meta store
        $GLOBALS['btr_test_meta'] = [];
    }

    public function testCalculateTotalPricingWithRoomsExtraNightsAndExtras(): void
    {
        $packageId = 123;
        // Adult base room price (fallback _prezzo_doppia)
        update_post_meta($packageId, '_prezzo_doppia', 300.0);
        // Extra night price per person
        update_post_meta($packageId, '_prezzo_notte_extra_pp', 40.0);

        $params = [
            'package_id' => $packageId,
            'participants' => [
                'adults' => 2,
                'children' => [ 'f1' => 1, 'f2' => 0, 'f3' => 0, 'f4' => 0 ],
            ],
            'rooms' => [
                [ 'type' => 'doppia', 'adults' => 2, 'children' => [ 'f1' => 1, 'f2' => 0, 'f3' => 0, 'f4' => 0 ] ],
            ],
            'extra_nights' => 1,
            'extra_costs' => [
                [ 'name' => 'Aggregated Extras', 'price' => 10.0, 'quantity' => 1, 'applies_to' => 'all' ],
            ],
        ];

        $calc = BTR_Unified_Calculator::get_instance();
        $result = $calc->calculate_total_pricing($params);

        // Camere: 2 adulti * 300 + 1 bambino f1 * 300*0.70 = 600 + 210 = 810
        $this->assertSame(810.0, $result['totale_camere']);

        // Extra notti: adulti 2*40*1 = 80; bambino f1: 40*0.375*1 + supplemento(10) = 15 + 10 = 25; totale = 105
        $this->assertSame(105.0, $result['totale_notti_extra']);

        // Extra costs: applies_to all: 3 partecipanti * 10 = 30
        $this->assertSame(30.0, $result['totale_costi_extra']);

        // Totale generale = 810 + 105 + 30 = 945
        $this->assertSame(945.0, $result['totale_generale']);
    }

    public function testChildPercentagesHelpers(): void
    {
        $this->assertSame(0.375, BTR_Unified_Calculator::get_child_extra_night_percentage('f1'));
        $this->assertSame(0.5, BTR_Unified_Calculator::get_child_extra_night_percentage('f2'));
        $this->assertSame(0.7, BTR_Unified_Calculator::get_child_extra_night_percentage('f3'));
        $this->assertSame(0.8, BTR_Unified_Calculator::get_child_extra_night_percentage('f4'));

        $this->assertSame(0.70, BTR_Unified_Calculator::get_child_base_price_percentage('f1'));
        $this->assertSame(0.75, BTR_Unified_Calculator::get_child_base_price_percentage('f2'));
        $this->assertSame(0.80, BTR_Unified_Calculator::get_child_base_price_percentage('f3'));
        $this->assertSame(0.85, BTR_Unified_Calculator::get_child_base_price_percentage('f4'));
    }
}

