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
        $group_assignments = $payment_context['group_assignments'] ?? array();
        
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
        
        $normalized_mode = strtolower((string) $payment_mode);
        $is_group_payment = false !== strpos($normalized_mode, 'gruppo');
        $is_organizer = $is_group_payment && (float) $payment_amount === 0.0;

        $clean_assignments = array();
        if (is_array($group_assignments)) {
            foreach ($group_assignments as $assignment) {
                if (!is_array($assignment)) {
                    continue;
                }

                $name = isset($assignment['name']) ? sanitize_text_field((string) $assignment['name']) : '';
                if ('' === $name) {
                    continue;
                }

                $shares = isset($assignment['shares']) ? (int) $assignment['shares'] : 1;
                if ($shares < 1) {
                    $shares = 1;
                }

                $share_label = sanitize_text_field(_n('quota', 'quote', $shares, 'born-to-ride-booking'));

                $clean_assignments[] = array(
                    'name' => $name,
                    'shares' => $shares,
                    'share_label' => $share_label,
                );
            }
        }

        ob_start();
        ?>
        <div class="btr-checkout-payment-context-block" style="text-align: <?php echo esc_attr($alignment); ?>;">
            <div class="btr-checkout-payment-context <?php echo esc_attr($gradient_class); ?>" data-payment-mode="<?php echo esc_attr($payment_mode); ?>">
                <div class="btr-checkout-payment-context__header">
                    <div class="btr-checkout-payment-context__identity">
                        <?php if ($show_icon): ?>
                            <span class="btr-checkout-payment-context__icon" aria-hidden="true"><?php echo esc_html($icon); ?></span>
                        <?php endif; ?>

                        <div class="btr-checkout-payment-context__headline">
                            <span class="btr-checkout-payment-context__eyebrow"><?php esc_html_e('Modalità pagamento', 'born-to-ride-booking'); ?></span>
                            <span class="btr-payment-mode"><?php echo esc_html($mode_label); ?></span>
                        </div>
                    </div>

                    <?php if ($preventivo_id): ?>
                        <span class="btr-checkout-payment-context__pill">
                            <?php printf(
                                esc_html__('Preventivo #%s', 'born-to-ride-booking'),
                                esc_html($preventivo_id)
                            ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($is_organizer): ?>
                    <div class="btr-checkout-payment-context__notice">
                        <?php esc_html_e("Sei l'organizzatore: nessun pagamento è richiesto adesso.", 'born-to-ride-booking'); ?>
                    </div>
                <?php endif; ?>

                <?php
                $participants_value = '';
                if ($participants_info) {
                    if (is_array($participants_info)) {
                        $total = isset($participants_info['total']) ? (int) $participants_info['total'] : 0;
                        $breakdown = isset($participants_info['breakdown']) ? sanitize_text_field((string) $participants_info['breakdown']) : '';

                        if ($total > 0) {
                            $participants_value = (string) $total;
                            if ($breakdown) {
                                $participants_value .= ' (' . $breakdown . ')';
                            }
                        } elseif ($breakdown) {
                            $participants_value = $breakdown;
                        }
                    } else {
                        $participants_value = sanitize_text_field((string) $participants_info);
                    }
                }
                ?>

                <?php if ($participants_value || $payment_amount || !empty($clean_assignments)): ?>
                    <div class="btr-checkout-payment-context__meta">
                        <?php if ($participants_value): ?>
                            <div class="btr-checkout-payment-context__meta-item">
                                <span class="btr-checkout-payment-context__meta-icon" aria-hidden="true">👥</span>
                                <div class="btr-checkout-payment-context__meta-copy">
                                    <span class="btr-checkout-payment-context__meta-label"><?php esc_html_e('Partecipanti', 'born-to-ride-booking'); ?></span>
                                    <span class="btr-checkout-payment-context__meta-value"><?php echo esc_html($participants_value); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($payment_amount): ?>
                            <div class="btr-checkout-payment-context__meta-item">
                                <span class="btr-checkout-payment-context__meta-icon" aria-hidden="true">💰</span>
                                <div class="btr-checkout-payment-context__meta-copy">
                                    <span class="btr-checkout-payment-context__meta-label"><?php esc_html_e('Importo dovuto', 'born-to-ride-booking'); ?></span>
                                    <span class="btr-checkout-payment-context__meta-value"><?php echo wp_kses_post( wc_price( (float) $payment_amount ) ); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($clean_assignments)): ?>
                            <div class="btr-checkout-payment-context__meta-item">
                                <span class="btr-checkout-payment-context__meta-icon" aria-hidden="true">💳</span>
                                <div class="btr-checkout-payment-context__meta-copy">
                                    <span class="btr-checkout-payment-context__meta-label"><?php esc_html_e('Paganti', 'born-to-ride-booking'); ?></span>
                                    <div class="btr-checkout-payment-context__chips">
                                        <?php foreach ($clean_assignments as $assignment): ?>
                                            <?php
                                            $quantity_display = '(' . $assignment['shares'] . ' ' . $assignment['share_label'] . ')';
                                            ?>
                                            <span class="btr-checkout-payment-context__chip">
                                                <?php echo esc_html($assignment['name']); ?>
                                                <span class="btr-checkout-payment-context__chip-quantity"><?php echo esc_html($quantity_display); ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
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
                    'group_assignments' => $cart_item[BTR_Checkout_Context_Manager::GROUP_ASSIGNMENTS_KEY] ?? array(),
                ];
            }
        }

        return [];
    }
}

// Inizializza il blocco
new BTR_Payment_Context_Block();
