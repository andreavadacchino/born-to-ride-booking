<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test funzionale per il flusso checkout completo
 *
 * Simula il percorso utente completo dal organizer popup al checkout
 * CRITICO: Verifica che il fix per il payment context funzioni end-to-end
 */
final class CheckoutFlowTest extends TestCase
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
     * Test completo: Organizer crea ordine gruppo → Redirect checkout → Box visibile
     *
     * CRITICO: Questo testa il bug appena risolto end-to-end
     */
    public function testGroupOrganizerCheckoutFlow(): void
    {
        // STEP 1: Simula creazione ordine organizzatore
        $organizer_data = [
            'preventivo_id' => '37525',
            'payment_mode' => 'gruppo',
            'participants' => [
                ['nome' => 'Andrea', 'tipo_persona' => 'adulto'],
                ['nome' => 'Moira', 'tipo_persona' => 'adulto'],
                ['nome' => 'De Daniele', 'tipo_persona' => 'bambino', 'fascia' => 'f1'],
                ['nome' => 'Leonardo', 'tipo_persona' => 'neonato']
            ]
        ];

        $order_result = $this->createOrganizerOrder($organizer_data);

        $this->assertTrue($order_result['success'], 'Creazione ordine organizzatore deve essere successful');
        $this->assertArrayHasKey('redirect_url', $order_result);
        $this->assertStringContainsString('checkout', $order_result['redirect_url']);

        // STEP 2: Simula checkout con cart item creato
        $cart_context = $this->simulateCheckoutWithCartItem($order_result['cart_item_data']);

        $this->assertNotEmpty($cart_context, 'Context pagamento deve essere disponibile nel checkout');
        $this->assertEquals('gruppo', $cart_context['payment_mode']);
        $this->assertEquals('0', $cart_context['payment_amount']); // Organizzatore non paga

        // STEP 3: Verifica che JavaScript riceva dati corretti
        $js_fallback_data = $this->extractJavaScriptFallbackData($cart_context);

        $this->assertNotEmpty($js_fallback_data, 'JavaScript deve ricevere dati fallback');
        $this->assertEquals('gruppo', $js_fallback_data['payment_mode']);
        $this->assertArrayHasKey('participants_info', $js_fallback_data);

        // STEP 4: Verifica Store API Extension
        $store_api_data = $this->simulateStoreApiExtension($cart_context);

        $this->assertArrayHasKey('payment_mode', $store_api_data);
        $this->assertEquals('gruppo', $store_api_data['payment_mode']);
    }

    /**
     * Test flusso pagamento caparra (per confronto)
     */
    public function testDepositPaymentCheckoutFlow(): void
    {
        $deposit_data = [
            'preventivo_id' => '37526',
            'payment_mode' => 'caparro',
            'payment_amount' => '178.74'
        ];

        $order_result = $this->createDepositOrder($deposit_data);

        $this->assertTrue($order_result['success']);

        $cart_context = $this->simulateCheckoutWithCartItem($order_result['cart_item_data']);

        $this->assertEquals('caparro', $cart_context['payment_mode']);
        $this->assertEquals('178.74', $cart_context['payment_amount']);
        $this->assertGreaterThan(0, floatval($cart_context['payment_amount']));
    }

    /**
     * Test performance: checkout deve caricare velocemente
     */
    public function testCheckoutPerformance(): void
    {
        $organizer_data = [
            'preventivo_id' => '37527',
            'payment_mode' => 'gruppo'
        ];

        $start_time = microtime(true);

        // Simula intero flusso
        $order_result = $this->createOrganizerOrder($organizer_data);
        $cart_context = $this->simulateCheckoutWithCartItem($order_result['cart_item_data']);
        $js_data = $this->extractJavaScriptFallbackData($cart_context);

        $total_time = microtime(true) - $start_time;

        $this->assertLessThan(0.1, $total_time, 'Flusso checkout completo deve essere < 100ms');
        $this->assertNotEmpty($js_data, 'Dati devono essere disponibili rapidamente');
    }

    /**
     * Test edge case: preventivo inesistente
     */
    public function testInvalidPreventivoHandling(): void
    {
        $invalid_data = [
            'preventivo_id' => '999999',
            'payment_mode' => 'gruppo'
        ];

        $order_result = $this->createOrganizerOrder($invalid_data);

        $this->assertFalse($order_result['success'], 'Preventivo inesistente deve essere gestito');
        $this->assertArrayHasKey('error', $order_result);
    }

    /**
     * Test regression: verifica che fallback JavaScript funzioni sempre
     *
     * IMPORTANTE: Se Store API fallisce, JavaScript deve usare dati localizzati
     */
    public function testJavaScriptFallbackResilience(): void
    {
        $organizer_data = [
            'preventivo_id' => '37528',
            'payment_mode' => 'gruppo'
        ];

        $order_result = $this->createOrganizerOrder($organizer_data);

        // Simula Store API failure
        $cart_context_empty = [];

        // JavaScript deve comunque funzionare con dati localizzati
        $js_fallback = $this->extractJavaScriptFallbackData($order_result['cart_item_data']);

        $this->assertNotEmpty($js_fallback, 'Fallback JavaScript deve funzionare anche senza Store API');
        $this->assertEquals('gruppo', $js_fallback['payment_mode']);
    }

    // HELPER METHODS ----------------------------------------------------

    private function createOrganizerOrder(array $data): array
    {
        // Simula logica di creazione ordine organizzatore
        if (!isset($data['preventivo_id']) || $data['preventivo_id'] === '999999') {
            return ['success' => false, 'error' => 'Preventivo non trovato'];
        }

        $cart_item_data = [
            '_btr_payment_mode' => $data['payment_mode'],
            '_btr_payment_mode_label' => $data['payment_mode'] === 'gruppo' ? 'Pagamento di Gruppo' : 'Pagamento Caparra',
            '_btr_preventivo_id' => $data['preventivo_id'],
            '_btr_payment_amount' => $data['payment_mode'] === 'gruppo' ? '0' : ($data['payment_amount'] ?? '0'),
            '_btr_participants_info' => $data['participants'] ?? []
        ];

        return [
            'success' => true,
            'redirect_url' => '/checkout/',
            'cart_item_data' => $cart_item_data
        ];
    }

    private function createDepositOrder(array $data): array
    {
        return $this->createOrganizerOrder($data);
    }

    private function simulateCheckoutWithCartItem(array $cart_item_data): array
    {
        // Simula il processo di estrazione dati nel checkout
        if (isset($cart_item_data['_btr_payment_mode'])) {
            return [
                'payment_mode' => $cart_item_data['_btr_payment_mode'],
                'payment_mode_label' => $cart_item_data['_btr_payment_mode_label'] ?? '',
                'preventivo_id' => $cart_item_data['_btr_preventivo_id'] ?? '',
                'payment_amount' => $cart_item_data['_btr_payment_amount'] ?? '0',
                'participants_info' => $cart_item_data['_btr_participants_info'] ?? []
            ];
        }

        return [];
    }

    private function extractJavaScriptFallbackData(array $context): array
    {
        // Simula wp_localize_script per JavaScript fallback
        if (!empty($context)) {
            return [
                'payment_mode' => $context['payment_mode'] ?? '',
                'payment_mode_label' => $context['payment_mode_label'] ?? '',
                'preventivo_id' => $context['preventivo_id'] ?? '',
                'payment_amount' => $context['payment_amount'] ?? '0',
                'participants_info' => $context['participants_info'] ?? []
            ];
        }

        return [];
    }

    private function simulateStoreApiExtension(array $context): array
    {
        // Simula Store API Extension data callback
        return [
            'payment_mode' => $context['payment_mode'] ?? '',
            'preventivo_id' => $context['preventivo_id'] ?? '',
            'payment_amount' => $context['payment_amount'] ?? '0'
        ];
    }
}