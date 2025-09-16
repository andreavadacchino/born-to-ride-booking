<?php
/**
 * BTR Payment Context Block
 * Blocco custom per mostrare il contesto pagamento nel checkout WooCommerce
 * 
 * @package BornToRideBooking
 * @since 1.0.240
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Context_Block {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        // Assicura che il blocco sia consentito come inner block nel Payment Block
        add_filter('woocommerce_blocks_inner_blocks_allowed_block_types', [$this, 'allow_in_wc_payment_inner_blocks'], 10, 2);
    }
    
    /**
     * Registra il blocco
     */
    public function register_block() {
        // Verifica che le funzioni WP siano disponibili
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('born-to-ride/payment-context', [
            'editor_script'   => 'btr-payment-context-block-editor',
            'editor_style'    => 'btr-payment-context-block-editor-style',
            'style'           => 'btr-payment-context-block-style',
            'render_callback' => [$this, 'render_block'],
            'attributes'      => [
                'alignment' => [
                    'type'    => 'string',
                    'default' => 'center',
                ],
                'showIcon' => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
            ],
        ]);
        
        error_log('BTR Payment Context Block: Blocco registrato');
    }

    /**
     * Garantisce che il blocco sia consentito nei container inner-block di WooCommerce
     * in particolare dentro il Payment Block del checkout.
     *
     * @param array $allowed_blocks
     * @param WP_Block|null $block_instance
     * @return array
     */
    public function allow_in_wc_payment_inner_blocks( $allowed_blocks, $block_instance = null ) {
        if ( ! is_array( $allowed_blocks ) ) {
            return $allowed_blocks;
        }

        $target_parents = [ 'woocommerce/checkout-payment-block' ];

        if ( $block_instance && isset( $block_instance->name ) ) {
            if ( in_array( $block_instance->name, $target_parents, true ) ) {
                if ( ! in_array( 'born-to-ride/payment-context', $allowed_blocks, true ) ) {
                    $allowed_blocks[] = 'born-to-ride/payment-context';
                }
            }
        }

        return $allowed_blocks;
    }
    
    /**
     * Enqueue assets per l'editor blocchi
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'btr-payment-context-block-editor',
            BTR_PLUGIN_URL . 'assets/js/btr-payment-context-block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-i18n',
                'wp-data',
            ],
            BTR_VERSION . '.240',
            true
        );
        
        wp_enqueue_style(
            'btr-payment-context-block-editor-style',
            BTR_PLUGIN_URL . 'assets/css/btr-checkout-context.css',
            [],
            BTR_VERSION . '.240'
        );
        
        // Enqueue filter per inner block registration
        wp_enqueue_script(
            'btr-payment-block-filter',
            BTR_PLUGIN_URL . 'assets/js/btr-payment-block-filter.js',
            [
                'wc-blocks-checkout',
                'wp-element',
            ],
            BTR_VERSION . '.240',
            true
        );
        
        error_log('BTR Payment Context Block: Asset editor caricati');
    }
    
    /**
     * Enqueue assets per il frontend
     */
    public function enqueue_frontend_assets() {
        // Solo nel checkout
        if (!is_checkout()) {
            return;
        }
        
        wp_enqueue_style(
            'btr-payment-context-block-style',
            BTR_PLUGIN_URL . 'assets/css/btr-checkout-context.css',
            [],
            BTR_VERSION . '.240'
        );
        
        wp_enqueue_script(
            'btr-payment-context-block-frontend',
            BTR_PLUGIN_URL . 'assets/js/btr-payment-context-block-frontend.js',
            ['wp-element', 'wp-data'],
            BTR_VERSION . '.240',
            true
        );
        
        error_log('BTR Payment Context Block: Asset frontend caricati');
    }
    
    /**
     * Render del blocco (server-side)
     */
    public function render_block($attributes, $content) {
        // Verifica che siamo nel checkout
        if (!is_checkout() || is_wc_endpoint_url()) {
            return '';
        }
        
        // Get context manager instance
        $context_manager = BTR_Checkout_Context_Manager::get_instance();
        
        // Ottieni i dati del contesto pagamento
        $payment_context = $this->get_payment_context_data();
        
        if (empty($payment_context['payment_mode'])) {
            return '';
        }
        
        // Genera l'HTML del blocco
        $alignment = $attributes['alignment'] ?? 'center';
        $show_icon = $attributes['showIcon'] ?? true;
        
        $payment_mode = $payment_context['payment_mode'];
        $mode_label = $payment_context['payment_mode_label'] ?? ucfirst($payment_mode);
        $preventivo_id = $payment_context['preventivo_id'] ?? '';
        $participants_info = $payment_context['participants_info'] ?? '';
        $payment_amount = $payment_context['payment_amount'] ?? '';
        
        // Determina stile basato su modalità
        $gradient_class = 'btr-payment-context-default';
        $icon = '💳';
        
        switch ($payment_mode) {
            case 'caparro':
                $gradient_class = 'btr-payment-context-caparro';
                $icon = '💰';
                break;
            case 'gruppo':
                $gradient_class = 'btr-payment-context-gruppo';
                $icon = '👥';
                break;
            case 'completo':
                $gradient_class = 'btr-payment-context-completo';
                $icon = '✅';
                break;
        }
        
        ob_start();
        ?>
        <div class="btr-checkout-payment-context-block" style="text-align: <?php echo esc_attr($alignment); ?>;">
            <div class="btr-checkout-payment-context <?php echo esc_attr($gradient_class); ?>">
                <h3 class="btr-payment-title">
                    <?php if ($show_icon): ?>
                        <span class="btr-payment-icon"><?php echo $icon; ?></span>
                    <?php endif; ?>
                    <?php esc_html_e('Modalità di Pagamento Selezionata', 'born-to-ride-booking'); ?>
                </h3>
                
                <p class="btr-payment-mode"><?php echo esc_html($mode_label); ?></p>
                
                <?php if ($participants_info): ?>
                    <p class="btr-payment-participants">
                        <?php
                        // Formatta array/oggetto in stringa leggibile
                        if (is_array($participants_info)) {
                            $total = isset($participants_info['total']) ? (int) $participants_info['total'] : 0;
                            $breakdown = isset($participants_info['breakdown']) ? (string) $participants_info['breakdown'] : '';
                            /* translators: 1: numero partecipanti, 2: dettaglio */
                            printf('👥 %s: %d %s%s%s',
                                esc_html__('Partecipanti', 'born-to-ride-booking'),
                                $total,
                                $breakdown ? '(' : '',
                                esc_html($breakdown),
                                $breakdown ? ')' : ''
                            );
                        } else {
                            echo '👥 ' . esc_html__('Partecipanti:', 'born-to-ride-booking') . ' ' . esc_html((string) $participants_info);
                        }
                        ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($payment_amount): ?>
                    <p class="btr-payment-amount">
                        💰 <?php esc_html_e('Importo:', 'born-to-ride-booking'); ?> <?php echo wp_kses_post( wc_price( (float) $payment_amount ) ); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($preventivo_id): ?>
                    <p class="btr-payment-preventivo">
                        📋 <?php esc_html_e('Preventivo', 'born-to-ride-booking'); ?> #<?php echo esc_html($preventivo_id); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Ottieni dati contesto pagamento dal carrello
     */
    private function get_payment_context_data() {
        if (!WC()->cart) {
            return [];
        }
        
        $cart_items = WC()->cart->get_cart();
        
        foreach ($cart_items as $cart_item) {
            if (isset($cart_item[BTR_Checkout_Context_Manager::PAYMENT_MODE_META_KEY])) {
                return [
                    'payment_mode' => $cart_item[BTR_Checkout_Context_Manager::PAYMENT_MODE_META_KEY],
                    'payment_mode_label' => $cart_item[BTR_Checkout_Context_Manager::PAYMENT_MODE_LABEL_KEY] ?? '',
                    'preventivo_id' => $cart_item[BTR_Checkout_Context_Manager::PREVENTIVO_ID_KEY] ?? '',
                    'participants_info' => $cart_item[BTR_Checkout_Context_Manager::PARTICIPANTS_INFO_KEY] ?? '',
                    'payment_amount' => $cart_item[BTR_Checkout_Context_Manager::PAYMENT_AMOUNT_KEY] ?? '',
                ];
            }
        }
        
        return [];
    }
}

// Inizializza il blocco
new BTR_Payment_Context_Block();
