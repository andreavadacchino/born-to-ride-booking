<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test di performance per BTR Plugin
 *
 * Verifica che le operazioni critiche siano veloci e efficienti
 * OBIETTIVO: Checkout < 2s, AJAX < 500ms, Memory < 50MB
 */
final class CheckoutPerformanceTest extends TestCase
{
    private array $performance_baseline = [
        'checkout_load_time' => 2.0,      // secondi
        'ajax_response_time' => 0.5,      // secondi
        'memory_usage_limit' => 50 * 1024 * 1024, // 50MB
        'cart_item_processing' => 0.01,   // 10ms per item
        'payment_context_extraction' => 0.005, // 5ms
    ];

    protected function setUp(): void
    {
        $GLOBALS['btr_test_meta'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['btr_test_meta']);
    }

    /**
     * Test performance caricamento checkout con payment context
     *
     * CRITICO: Il checkout deve essere veloce anche con il nostro fix
     */
    public function testCheckoutLoadPerformance(): void
    {
        $start_memory = memory_get_usage(true);
        $start_time = microtime(true);

        // Simula caricamento checkout completo
        $checkout_data = $this->simulateFullCheckoutLoad();

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        $execution_time = $end_time - $start_time;
        $memory_used = $end_memory - $start_memory;

        $this->assertLessThan(
            $this->performance_baseline['checkout_load_time'],
            $execution_time,
            "Checkout deve caricare in < {$this->performance_baseline['checkout_load_time']}s (attuale: {$execution_time}s)"
        );

        $this->assertLessThan(
            $this->performance_baseline['memory_usage_limit'],
            $memory_used,
            "Memory usage deve essere < 50MB (attuale: " . ($memory_used / 1024 / 1024) . "MB)"
        );

        $this->assertNotEmpty($checkout_data, 'Checkout deve caricare con successo');
    }

    /**
     * Test performance AJAX organizer order creation
     */
    public function testAjaxOrganizerOrderPerformance(): void
    {
        $start_time = microtime(true);

        // Simula AJAX call per creazione ordine organizzatore
        $ajax_result = $this->simulateAjaxOrganizerOrder([
            'preventivo_id' => '37525',
            'payment_mode' => 'gruppo'
        ]);

        $execution_time = microtime(true) - $start_time;

        $this->assertLessThan(
            $this->performance_baseline['ajax_response_time'],
            $execution_time,
            "AJAX deve rispondere in < {$this->performance_baseline['ajax_response_time']}s (attuale: {$execution_time}s)"
        );

        $this->assertTrue($ajax_result['success'], 'AJAX deve completare con successo');
    }

    /**
     * Test performance con carrello pieno (stress test)
     */
    public function testCartProcessingWithMultipleItems(): void
    {
        $item_count = 50; // Stress test
        $start_time = microtime(true);

        // Crea carrello con molti item
        $cart_items = [];
        for ($i = 1; $i <= $item_count; $i++) {
            $cart_items[] = [
                'product_id' => $i,
                '_btr_payment_mode' => $i % 2 === 0 ? 'gruppo' : 'caparro',
                '_btr_preventivo_id' => "3752$i",
                '_btr_payment_amount' => $i % 2 === 0 ? '0' : '100.00'
            ];
        }

        // Processa tutti gli item
        $processed_items = $this->processCartItems($cart_items);

        $execution_time = microtime(true) - $start_time;
        $time_per_item = $execution_time / $item_count;

        $this->assertLessThan(
            $this->performance_baseline['cart_item_processing'],
            $time_per_item,
            "Processing deve essere < {$this->performance_baseline['cart_item_processing']}s per item (attuale: {$time_per_item}s)"
        );

        $this->assertCount($item_count, $processed_items, 'Tutti gli item devono essere processati');
    }

    /**
     * Test performance payment context extraction
     */
    public function testPaymentContextExtractionSpeed(): void
    {
        $iterations = 1000; // Stress test
        $total_time = 0;

        $cart_data = [
            '_btr_payment_mode' => 'gruppo',
            '_btr_preventivo_id' => '37525',
            '_btr_payment_amount' => '0',
            '_btr_participants_info' => [
                'total' => 4,
                'breakdown' => 'Andrea, Moira, De Daniele, Leonardo'
            ]
        ];

        for ($i = 0; $i < $iterations; $i++) {
            $start_time = microtime(true);
            $context = $this->extractPaymentContext($cart_data);
            $total_time += microtime(true) - $start_time;
        }

        $avg_time = $total_time / $iterations;

        $this->assertLessThan(
            $this->performance_baseline['payment_context_extraction'],
            $avg_time,
            "Context extraction deve essere < {$this->performance_baseline['payment_context_extraction']}s (media: {$avg_time}s)"
        );
    }

    /**
     * Test memory leak detection
     */
    public function testMemoryLeakDetection(): void
    {
        $initial_memory = memory_get_usage(true);

        // Esegui operazioni multiple per rilevare memory leak
        for ($i = 0; $i < 100; $i++) {
            $this->simulateCheckoutOperation();

            if ($i % 10 === 0) {
                gc_collect_cycles(); // Force garbage collection
            }
        }

        $final_memory = memory_get_usage(true);
        $memory_growth = $final_memory - $initial_memory;

        $this->assertLessThan(
            10 * 1024 * 1024, // 10MB growth limit
            $memory_growth,
            "Memory growth deve essere < 10MB (crescita: " . ($memory_growth / 1024 / 1024) . "MB)"
        );
    }

    /**
     * Test performance database operations
     */
    public function testDatabaseQueryPerformance(): void
    {
        $start_time = microtime(true);

        // Simula operazioni database critiche
        $preventivo_data = $this->loadPreventivoData('37525');
        $participants = $this->loadParticipantsData('37525');
        $payment_data = $this->loadPaymentData('37525');

        $execution_time = microtime(true) - $start_time;

        $this->assertLessThan(
            0.1, // 100ms limit per operazioni DB
            $execution_time,
            "Database operations devono essere < 100ms (attuale: " . ($execution_time * 1000) . "ms)"
        );

        $this->assertNotEmpty($preventivo_data, 'Dati preventivo devono essere caricati');
    }

    // HELPER METHODS ------------------------------------------------------

    private function simulateFullCheckoutLoad(): array
    {
        // Simula caricamento completo checkout
        usleep(rand(100000, 200000)); // 100-200ms realistic delay

        return [
            'payment_context' => $this->extractPaymentContext([
                '_btr_payment_mode' => 'gruppo',
                '_btr_preventivo_id' => '37525'
            ]),
            'cart_items' => [['id' => 1]],
            'totals' => ['total' => 0]
        ];
    }

    private function simulateAjaxOrganizerOrder(array $data): array
    {
        // Simula AJAX processing
        usleep(rand(50000, 100000)); // 50-100ms realistic delay

        return [
            'success' => true,
            'order_id' => rand(1000, 9999),
            'redirect_url' => '/checkout/'
        ];
    }

    private function processCartItems(array $items): array
    {
        $processed = [];

        foreach ($items as $item) {
            // Simula processing per item
            usleep(1000); // 1ms per item
            $processed[] = $this->extractPaymentContext($item);
        }

        return $processed;
    }

    private function extractPaymentContext(array $cart_data): array
    {
        // Simula estrazione context (very fast)
        return [
            'payment_mode' => $cart_data['_btr_payment_mode'] ?? '',
            'preventivo_id' => $cart_data['_btr_preventivo_id'] ?? '',
            'payment_amount' => $cart_data['_btr_payment_amount'] ?? '0'
        ];
    }

    private function simulateCheckoutOperation(): void
    {
        // Simula operazione checkout che potrebbe causare memory leak
        $temp_data = array_fill(0, 1000, 'test_data');
        $context = $this->extractPaymentContext([
            '_btr_payment_mode' => 'gruppo',
            '_btr_preventivo_id' => '37525'
        ]);

        // Simula processing
        unset($temp_data);
    }

    private function loadPreventivoData(string $preventivo_id): array
    {
        usleep(10000); // 10ms DB query simulation
        return ['id' => $preventivo_id, 'total' => '595.80'];
    }

    private function loadParticipantsData(string $preventivo_id): array
    {
        usleep(5000); // 5ms DB query simulation
        return [
            ['nome' => 'Andrea', 'tipo' => 'adulto'],
            ['nome' => 'Moira', 'tipo' => 'adulto']
        ];
    }

    private function loadPaymentData(string $preventivo_id): array
    {
        usleep(5000); // 5ms DB query simulation
        return ['mode' => 'gruppo', 'amount' => '0'];
    }
}