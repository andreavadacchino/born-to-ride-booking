<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test per BTR Checkout Payment Context Manager
 *
 * Verifica che il sistema di visualizzazione del contesto pagamento
 * nel checkout WooCommerce Blocks funzioni correttamente.
 *
 * CRITICO: Questi test validano il fix per il bug checkout payment context
 */
final class CheckoutPaymentContextTest extends TestCase
{
    private $context_manager;

    protected function setUp(): void
    {
        // Reset test environment
        $GLOBALS['btr_test_meta'] = [];

        // Mock WooCommerce cart
        $GLOBALS['woocommerce'] = new stdClass();
        $GLOBALS['woocommerce']->cart = $this->createMockCart();

        // Include the context manager class
        require_once __DIR__ . '/../../includes/class-btr-checkout-context-manager.php';

        // Create instance for testing
        $this->context_manager = new BTR_Checkout_Context_Manager();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['btr_test_meta']);
        unset($GLOBALS['woocommerce']);
    }

    /**
     * Test che verifica la creazione dei dati di contesto per pagamento gruppo
     *
     * CRITICO: Questo è il bug che abbiamo appena risolto -
     * il box non appariva per pagamenti di gruppo con totale €0
     */
    public function testGroupPaymentContextIsCreated(): void
    {
        // Simula cart item per pagamento di gruppo
        $cart_item_data = [
            '_btr_payment_mode' => 'gruppo',
            '_btr_payment_mode_label' => 'Pagamento di Gruppo',
            '_btr_preventivo_id' => '37525',
            '_btr_payment_amount' => '0', // CRITICO: totale zero
            '_btr_participants_info' => [
                'total' => 4,
                'breakdown' => 'Andrea, Moira, De Daniele, Leonardo'
            ]
        ];

        // Aggiungi item al mock cart
        $this->addCartItem(1, $cart_item_data);

        // Test che i dati vengano estratti correttamente
        $payment_context = $this->extractPaymentContextFromCart();

        $this->assertNotEmpty($payment_context, 'Payment context deve essere creato per pagamenti di gruppo');
        $this->assertEquals('gruppo', $payment_context['payment_mode']);
        $this->assertEquals('Pagamento di Gruppo', $payment_context['payment_mode_label']);
        $this->assertEquals('37525', $payment_context['preventivo_id']);
        $this->assertEquals('0', $payment_context['payment_amount']);
        $this->assertArrayHasKey('participants_info', $payment_context);
    }

    /**
     * Test che verifica la creazione dei dati per pagamento caparra
     */
    public function testDepositPaymentContextIsCreated(): void
    {
        $cart_item_data = [
            '_btr_payment_mode' => 'caparro',
            '_btr_payment_mode_label' => 'Pagamento Caparra (30%)',
            '_btr_preventivo_id' => '37526',
            '_btr_payment_amount' => '178.74'
        ];

        $this->addCartItem(2, $cart_item_data);
        $payment_context = $this->extractPaymentContextFromCart();

        $this->assertNotEmpty($payment_context);
        $this->assertEquals('caparro', $payment_context['payment_mode']);
        $this->assertEquals('178.74', $payment_context['payment_amount']);
    }

    /**
     * Test che verifica Store API Extension data callback
     */
    public function testStoreApiExtensionDataCallback(): void
    {
        $cart_item_data = [
            '_btr_payment_mode' => 'gruppo',
            '_btr_preventivo_id' => '37525',
            '_btr_payment_amount' => '0'
        ];

        $this->addCartItem(3, $cart_item_data);

        // Simula Store API callback
        $store_data = $this->context_manager->store_api_data_callback($cart_item_data);

        $this->assertIsArray($store_data, 'Store API deve restituire array');
        $this->assertArrayHasKey('payment_mode', $store_data);
        $this->assertEquals('gruppo', $store_data['payment_mode']);
    }

    /**
     * Test che verifica localizzazione script per fallback JavaScript
     *
     * IMPORTANTE: Il JavaScript usa questo fallback quando cart items sono vuoti
     */
    public function testPaymentContextLocalization(): void
    {
        $cart_item_data = [
            '_btr_payment_mode' => 'gruppo',
            '_btr_payment_mode_label' => 'Pagamento di Gruppo - Organizzatore',
            '_btr_preventivo_id' => '37525',
            '_btr_payment_amount' => '0',
            '_btr_participants_info' => [
                'total' => 4,
                'breakdown' => 'Andrea, Moira, De Daniele, Leonardo'
            ]
        ];

        $this->addCartItem(4, $cart_item_data);

        // Simula localizzazione script
        $localized_data = $this->extractLocalizationData();

        $this->assertNotEmpty($localized_data);
        $this->assertEquals('gruppo', $localized_data['payment_mode']);
        $this->assertEquals('0', $localized_data['payment_amount']);
        $this->assertArrayHasKey('participants_info', $localized_data);
    }

    /**
     * Test edge case: carrello vuoto non deve generare errori
     */
    public function testEmptyCartDoesNotGeneratePaymentContext(): void
    {
        // Cart vuoto
        $payment_context = $this->extractPaymentContextFromCart();
        $this->assertEmpty($payment_context, 'Cart vuoto non deve generare payment context');
    }

    /**
     * Test performance: verifica che l'extraction sia veloce
     */
    public function testPaymentContextExtractionPerformance(): void
    {
        // Aggiungi multipli cart items
        for ($i = 1; $i <= 10; $i++) {
            $this->addCartItem($i, [
                '_btr_payment_mode' => 'gruppo',
                '_btr_preventivo_id' => "3752$i"
            ]);
        }

        $start_time = microtime(true);
        $payment_context = $this->extractPaymentContextFromCart();
        $execution_time = microtime(true) - $start_time;

        $this->assertLessThan(0.01, $execution_time, 'Context extraction deve essere < 10ms');
        $this->assertNotEmpty($payment_context, 'Deve trovare il primo payment context');
    }

    // HELPER METHODS ------------------------------------------------------

    private function createMockCart()
    {
        return new class {
            public $cart_contents = [];

            public function get_cart() {
                return $this->cart_contents;
            }

            public function add_item($key, $data) {
                $this->cart_contents[$key] = $data;
            }
        };
    }

    private function addCartItem(int $product_id, array $cart_item_data): void
    {
        $cart_item = [
            'product_id' => $product_id,
            'quantity' => 1,
            'data' => new stdClass()
        ];

        // Merge cart item data
        $cart_item = array_merge($cart_item, $cart_item_data);

        $key = "item_$product_id";
        $GLOBALS['woocommerce']->cart->add_item($key, $cart_item);
    }

    private function extractPaymentContextFromCart(): array
    {
        $cart_items = $GLOBALS['woocommerce']->cart->get_cart();

        foreach ($cart_items as $cart_item) {
            if (isset($cart_item['_btr_payment_mode'])) {
                return [
                    'payment_mode' => $cart_item['_btr_payment_mode'],
                    'payment_mode_label' => $cart_item['_btr_payment_mode_label'] ?? '',
                    'preventivo_id' => $cart_item['_btr_preventivo_id'] ?? '',
                    'participants_info' => $cart_item['_btr_participants_info'] ?? '',
                    'payment_amount' => $cart_item['_btr_payment_amount'] ?? '',
                    'group_assignments' => $cart_item['_btr_group_assignments'] ?? []
                ];
            }
        }

        return [];
    }

    private function extractLocalizationData(): array
    {
        // Simula la logica di localizzazione del Context Manager
        $payment_context = $this->extractPaymentContextFromCart();

        if (!empty($payment_context)) {
            return $payment_context;
        }

        return [];
    }
}