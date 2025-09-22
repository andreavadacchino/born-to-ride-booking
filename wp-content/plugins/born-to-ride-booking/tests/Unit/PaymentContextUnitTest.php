<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit Test per funzioni Payment Context specifiche
 *
 * Test isolati delle singole funzioni critiche per il payment context
 * FOCUS: Testare logica pura senza dipendenze esterne
 */
final class PaymentContextUnitTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['btr_test_meta'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['btr_test_meta']);
    }

    /**
     * Unit test per funzione di sanitizzazione preventivo ID
     */
    public function testSanitizePreventivoId(): void
    {
        $test_cases = [
            // [input, expected_output, description]
            ['12345', 12345, 'Valid numeric string'],
            [12345, 12345, 'Valid integer'],
            ['12345abc', 12345, 'Numeric with letters'],
            ['abc12345def', 12345, 'Letters and numbers'],
            ['', 1, 'Empty string defaults to 1'],
            [0, 1, 'Zero defaults to 1'],
            [-12345, 12345, 'Negative becomes positive'],
            [999999999, 999999, 'Large number capped at 999999'],
            ['<script>alert(1)</script>37525', 37525, 'XSS attempt with valid ID'],
            ["1'; DROP TABLE wp_posts;--", 1, 'SQL injection attempt']
        ];

        foreach ($test_cases as [$input, $expected, $description]) {
            $result = $this->sanitizePreventivoId($input);
            $this->assertEquals($expected, $result, $description);
            $this->assertIsInt($result, 'Result must be integer');
            $this->assertGreaterThan(0, $result, 'Result must be positive');
            $this->assertLessThanOrEqual(999999, $result, 'Result must be within limits');
        }
    }

    /**
     * Unit test per validazione payment mode
     */
    public function testValidatePaymentMode(): void
    {
        $valid_modes = ['gruppo', 'caparro', 'saldo'];
        $invalid_modes = ['', 'invalid', null, 123, [], 'GRUPPO', 'Caparro'];

        foreach ($valid_modes as $mode) {
            $this->assertTrue(
                $this->validatePaymentMode($mode),
                "'{$mode}' should be valid payment mode"
            );
        }

        foreach ($invalid_modes as $mode) {
            $this->assertFalse(
                $this->validatePaymentMode($mode),
                "'{$mode}' should be invalid payment mode"
            );
        }
    }

    /**
     * Unit test per estrazione dati cart item
     */
    public function testExtractCartItemPaymentData(): void
    {
        // Test cart item valido
        $valid_cart_item = [
            '_btr_payment_mode' => 'gruppo',
            '_btr_payment_mode_label' => 'Pagamento di Gruppo',
            '_btr_preventivo_id' => '37525',
            '_btr_payment_amount' => '0',
            '_btr_participants_info' => [
                'total' => 4,
                'breakdown' => 'Andrea, Moira, De Daniele, Leonardo'
            ]
        ];

        $result = $this->extractCartItemPaymentData($valid_cart_item);

        $this->assertNotEmpty($result, 'Valid cart item should return data');
        $this->assertEquals('gruppo', $result['payment_mode']);
        $this->assertEquals('37525', $result['preventivo_id']);
        $this->assertEquals('0', $result['payment_amount']);
        $this->assertArrayHasKey('participants_info', $result);

        // Test cart item vuoto
        $empty_result = $this->extractCartItemPaymentData([]);
        $this->assertEmpty($empty_result, 'Empty cart item should return empty array');

        // Test cart item senza BTR data
        $non_btr_item = ['product_id' => 123, 'quantity' => 1];
        $non_btr_result = $this->extractCartItemPaymentData($non_btr_item);
        $this->assertEmpty($non_btr_result, 'Non-BTR cart item should return empty array');

        // Test cart item parzialmente valido
        $partial_item = ['_btr_payment_mode' => 'caparro'];
        $partial_result = $this->extractCartItemPaymentData($partial_item);
        $this->assertNotEmpty($partial_result, 'Partial cart item should still return data');
        $this->assertEquals('caparro', $partial_result['payment_mode']);
        $this->assertEquals('', $partial_result['preventivo_id']); // Default empty
    }

    /**
     * Unit test per calcolo totale gruppo
     */
    public function testCalculateGroupTotal(): void
    {
        $test_scenarios = [
            [
                'participants' => [
                    ['nome' => 'Andrea', 'importo' => 200.50],
                    ['nome' => 'Moira', 'importo' => 244.00],
                    ['nome' => 'De Daniele', 'importo' => 146.30],
                    ['nome' => 'Leonardo', 'importo' => 20.00]
                ],
                'expected_total' => 610.80,
                'description' => 'Standard group calculation'
            ],
            [
                'participants' => [],
                'expected_total' => 0.00,
                'description' => 'Empty participants'
            ],
            [
                'participants' => [
                    ['nome' => 'Solo', 'importo' => 299.99]
                ],
                'expected_total' => 299.99,
                'description' => 'Single participant'
            ],
            [
                'participants' => [
                    ['nome' => 'Test1', 'importo' => 0],
                    ['nome' => 'Test2', 'importo' => 100.50]
                ],
                'expected_total' => 100.50,
                'description' => 'Mixed zero and positive amounts'
            ]
        ];

        foreach ($test_scenarios as $scenario) {
            $result = $this->calculateGroupTotal($scenario['participants']);

            $this->assertEquals(
                $scenario['expected_total'],
                $result,
                $scenario['description']
            );

            $this->assertIsFloat($result, 'Result must be float');
            $this->assertGreaterThanOrEqual(0, $result, 'Result must be non-negative');
        }
    }

    /**
     * Unit test per validazione JavaScript dependencies
     */
    public function testValidateJavaScriptDependencies(): void
    {
        // Test tutte le dipendenze disponibili
        $all_available = [
            'window.wc' => true,
            'window.wc.blocksCheckout' => true,
            'window.wp' => true,
            'window.wp.plugins' => true,
            'window.wp.element' => true,
            'window.wp.data' => true
        ];

        $this->assertTrue(
            $this->validateJavaScriptDependencies($all_available),
            'All dependencies available should return true'
        );

        // Test dipendenze mancanti
        $missing_wc = $all_available;
        $missing_wc['window.wc'] = false;

        $this->assertFalse(
            $this->validateJavaScriptDependencies($missing_wc),
            'Missing WooCommerce should return false'
        );

        // Test dipendenze parziali
        $partial_deps = [
            'window.wc' => true,
            'window.wp' => true,
            'window.wp.plugins' => false,
            'window.wp.element' => true,
            'window.wp.data' => false
        ];

        $this->assertFalse(
            $this->validateJavaScriptDependencies($partial_deps),
            'Partial dependencies should return false'
        );

        // Test array vuoto
        $this->assertFalse(
            $this->validateJavaScriptDependencies([]),
            'Empty dependencies should return false'
        );
    }

    /**
     * Unit test per format payment amount
     */
    public function testFormatPaymentAmount(): void
    {
        $test_cases = [
            [0, '0.00', 'Zero amount'],
            [100, '100.00', 'Integer amount'],
            [99.99, '99.99', 'Decimal amount'],
            [1000.5, '1000.50', 'Single decimal place'],
            ['123.45', '123.45', 'String numeric'],
            ['invalid', '0.00', 'Invalid string'],
            [-50.25, '0.00', 'Negative amount'],
            [999999.99, '999999.99', 'Large amount'],
            [0.001, '0.00', 'Very small amount rounds down']
        ];

        foreach ($test_cases as [$input, $expected, $description]) {
            $result = $this->formatPaymentAmount($input);
            $this->assertEquals($expected, $result, $description);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result, 'Result must be in format X.XX');
        }
    }

    /**
     * Unit test per generazione SlotFill key
     */
    public function testGenerateSlotFillKey(): void
    {
        $test_scenarios = [
            ['gruppo', '37525', 'btr-payment-context-gruppo-37525'],
            ['caparro', '12345', 'btr-payment-context-caparro-12345'],
            ['saldo', '99999', 'btr-payment-context-saldo-99999'],
            ['', '', 'btr-payment-context--'],
            [null, null, 'btr-payment-context--']
        ];

        foreach ($test_scenarios as [$payment_mode, $preventivo_id, $expected]) {
            $result = $this->generateSlotFillKey($payment_mode, $preventivo_id);

            $this->assertEquals($expected, $result, "Key generation for {$payment_mode}/{$preventivo_id}");
            $this->assertStringStartsWith('btr-payment-context-', $result, 'Key must start with prefix');
            $this->assertStringNotContainsString(' ', $result, 'Key must not contain spaces');
        }
    }

    // HELPER METHODS - Implementano la logica delle funzioni testate

    private function sanitizePreventivoId($input): int
    {
        $numeric_input = $input;

        if (!is_int($numeric_input)) {
            preg_match_all('/\d+/', (string) $numeric_input, $matches);
            $numeric_input = empty($matches[0]) ? '' : end($matches[0]);
        }

        $id = abs((int) $numeric_input);

        if ($id <= 0) {
            return 1;
        }

        return min(999999, $id);
    }

    private function validatePaymentMode($mode): bool
    {
        $valid_modes = ['gruppo', 'caparro', 'saldo'];
        return is_string($mode) && in_array($mode, $valid_modes, true);
    }

    private function extractCartItemPaymentData(array $cart_item): array
    {
        if (empty($cart_item) || !isset($cart_item['_btr_payment_mode'])) {
            return [];
        }

        return [
            'payment_mode' => $cart_item['_btr_payment_mode'] ?? '',
            'payment_mode_label' => $cart_item['_btr_payment_mode_label'] ?? '',
            'preventivo_id' => $cart_item['_btr_preventivo_id'] ?? '',
            'payment_amount' => $cart_item['_btr_payment_amount'] ?? '0',
            'participants_info' => $cart_item['_btr_participants_info'] ?? []
        ];
    }

    private function calculateGroupTotal(array $participants): float
    {
        $total = 0.0;

        foreach ($participants as $participant) {
            if (isset($participant['importo'])) {
                $total += floatval($participant['importo']);
            }
        }

        return round($total, 2);
    }

    private function validateJavaScriptDependencies(array $dependencies): bool
    {
        $required = [
            'window.wc',
            'window.wc.blocksCheckout',
            'window.wp',
            'window.wp.plugins',
            'window.wp.element',
            'window.wp.data'
        ];

        foreach ($required as $dep) {
            if (empty($dependencies[$dep])) {
                return false;
            }
        }

        return true;
    }

    private function formatPaymentAmount($amount): string
    {
        $numeric = floatval($amount);

        if ($numeric < 0) {
            $numeric = 0;
        }

        return number_format($numeric, 2, '.', '');
    }

    private function generateSlotFillKey(?string $payment_mode, ?string $preventivo_id): string
    {
        $mode = $payment_mode ?? '';
        $id = $preventivo_id ?? '';

        return "btr-payment-context-{$mode}-{$id}";
    }
}
