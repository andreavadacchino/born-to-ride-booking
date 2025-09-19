<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PreventivoTotalsSmokeTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['btr_test_meta'] = [];
    }

    public function testSnapshotTotalsAreReturned(): void
    {
        $preventivo_id = 1001;
        $snapshot = [
            'rooms_total' => 900.0,
            'extra_nights' => [
                'total_corrected' => 120.0,
            ],
            'insurance' => [
                'total' => 45.0,
            ],
            'extra_costs' => [
                'total' => -20.0,
                'aggiunte' => 0.0,
                'riduzioni' => -20.0,
            ],
            'totals' => [
                'grand_total' => 1045.0,
                'supplements_total' => 0.0,
            ],
            'participants' => [],
            'timestamp' => '2025-09-16 10:00:00',
        ];
        $snapshot['integrity_hash'] = hash(
            'sha256',
            serialize([
                $snapshot['rooms_total'],
                $snapshot['totals']['grand_total'],
                $snapshot['participants'],
                $snapshot['timestamp'],
            ])
        );

        update_post_meta($preventivo_id, '_price_snapshot', $snapshot);
        update_post_meta($preventivo_id, '_has_price_snapshot', true);

        $calculator = btr_price_calculator();
        $result = $calculator->calculate_preventivo_total([
            'preventivo_id' => $preventivo_id,
        ]);

        $this->assertTrue($result['valid'], 'Il calcolo basato su snapshot deve essere valido');
        $this->assertSame('snapshot', $result['fonte']);
        $this->assertSame(1045.0, $result['totale_finale']);
        $this->assertSame(900.0, $result['base']);
        $this->assertSame(120.0, $result['extra_nights']);
        $this->assertSame(45.0, $result['assicurazioni']);
        $this->assertSame(-20.0, $result['extra_costs']);
    }

    public function testLegacyTotalsFallback(): void
    {
        $preventivo_id = 2002;

        update_post_meta($preventivo_id, '_prezzo_totale', 800.0);
        update_post_meta($preventivo_id, '_extra_night_total', 150.0);
        update_post_meta($preventivo_id, '_totale_assicurazioni', 60.0);
        update_post_meta($preventivo_id, '_totale_costi_extra', -30.0);
        update_post_meta($preventivo_id, '_totale_aggiunte_extra', 0.0);
        update_post_meta($preventivo_id, '_totale_sconti_riduzioni', -30.0);
        update_post_meta($preventivo_id, '_prezzo_totale_completo', 980.0);

        $calculator = btr_price_calculator();
        $result = $calculator->calculate_preventivo_total([
            'preventivo_id' => $preventivo_id,
        ]);

        $this->assertTrue($result['valid'], 'Il calcolo legacy deve essere valido');
        $this->assertSame('meta', $result['fonte']);
        $this->assertSame(980.0, $result['totale_finale']);
        $this->assertSame(650.0, $result['base']); // 800 - 150 notti extra
        $this->assertSame(150.0, $result['extra_nights']);
        $this->assertSame(60.0, $result['assicurazioni']);
        $this->assertSame(-30.0, $result['extra_costs']);
    }
}
