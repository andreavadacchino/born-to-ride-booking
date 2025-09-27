<?php
/**
 * BTR Checkout Context Manager
 * 
 * PROBLEMA RISOLTO: Il sistema attuale perde completamente il contesto della modalità
 * di pagamento (caparro/gruppo) quando l'utente passa al checkout WooCommerce.
 * 
 * SOLUZIONE: Persistenza del contesto attraverso Cart Item Meta Data e Store API
 * per garantire che l'utente veda sempre cosa sta pagando.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Checkout_Context_Manager {
    
    private static $instance = null;
    
    // Chiavi per metadata - CRITICHE per il funzionamento
    const PAYMENT_MODE_META_KEY = '_btr_payment_mode';
    const PAYMENT_MODE_LABEL_KEY = '_btr_payment_mode_label';
    const PREVENTIVO_ID_KEY = '_btr_preventivo_id';
    const PARTICIPANTS_INFO_KEY = '_btr_participants_info';
    const PAYMENT_AMOUNT_KEY = '_btr_payment_amount';
    const GROUP_ASSIGNMENTS_KEY = '_btr_group_assignments';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // CRITICO: Hook per aggiungere metadata al carrello
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_payment_context_to_cart'), 10, 3);
        
        // CRITICO: Hook per visualizzare metadata nel carrello/checkout
        add_filter('woocommerce_get_item_data', array($this, 'display_payment_context_in_cart'), 10, 2);
        
        // CRITICO: Hook per salvare metadata nell'ordine finale
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_payment_context_to_order_item'), 10, 4);
        
        // Store API Extension per WooCommerce Blocks (moderno)
        add_action('woocommerce_blocks_loaded', array($this, 'init_store_api_extension'));
        
        // Checkout display per tema classico
        add_action('woocommerce_review_order_before_payment', array($this, 'display_payment_mode_in_checkout'));
        
        // IMPORTANTE: Enqueue CSS per styling checkout
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_styles'));

        // FIX v1.0.244: Hook specifico per WooCommerce Blocks checkout scripts
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'enqueue_checkout_blocks_scripts'));
        
        // IMPORTANTE: Pulisci contesto dopo ordine completato
        add_action('woocommerce_thankyou', array($this, 'clear_payment_context'));
    }
    
    /**
     * Aggiunge contesto modalità pagamento ai dati del carrello
     * QUESTO È IL CUORE DELLA SOLUZIONE
     */
    public function add_payment_context_to_cart($cart_item_data, $product_id, $variation_id) {
        // Recupera contesto dalla sessione o POST
        $payment_mode = $this->get_current_payment_mode();
        $preventivo_id = $this->get_current_preventivo_id();
        
        if ($payment_mode && $preventivo_id) {
            // CRITICO: Questi dati seguiranno il prodotto ovunque
            $cart_item_data[self::PAYMENT_MODE_META_KEY] = $payment_mode;
            $cart_item_data[self::PAYMENT_MODE_LABEL_KEY] = $this->get_payment_mode_label($payment_mode);
            $cart_item_data[self::PREVENTIVO_ID_KEY] = $preventivo_id;
            $cart_item_data[self::PARTICIPANTS_INFO_KEY] = $this->get_participants_info($preventivo_id);
            
            // Importo specifico per modalità
            $payment_amount = $this->calculate_payment_amount($payment_mode, $preventivo_id);
            $cart_item_data[self::PAYMENT_AMOUNT_KEY] = $payment_amount;
            
            // CRITICO v1.0.238: Aggiungi custom_price SOLO per prodotto principale
            // NON sovrascrivere prezzi di assicurazioni e costi extra!
            
            // Controlla se è un'assicurazione o costo extra
            $is_insurance = isset($cart_item_data['from_assicurazione']) && $cart_item_data['from_assicurazione'];
            $is_extra = isset($cart_item_data['from_extra']) && $cart_item_data['from_extra'];
            $is_room = isset($cart_item_data['tipo_camera']) || isset($cart_item_data['totale_camera']);
            
            // Applica custom_price SOLO se NON è assicurazione/extra/camera
            if (!$is_insurance && !$is_extra && !$is_room) {
                if ($payment_mode === 'gruppo') {
                    // Organizzatore gruppo paga 0
                    $cart_item_data['custom_price'] = 0;
                    $cart_item_data['btr_order_type'] = 'group_organizer';
                    $cart_item_data[self::GROUP_ASSIGNMENTS_KEY] = $this->get_group_assignments($preventivo_id);
                } else if ($payment_mode === 'caparro') {
                    // Per caparra: NON sovrascrivere il prezzo qui
                    // La caparra è gestita da BTR_WooCommerce_Deposit_Integration tramite fee negativo
                    // Imposta i flag necessari per deposit integration
                    if (WC()->session) {
                        WC()->session->set('btr_deposit_mode', true);
                        WC()->session->set('btr_deposit_percentage', 30);
                        error_log('BTR Context Manager: Modalità caparra attivata - preservo prezzi originali, applico fee -70%');
                    }
                } else {
                    // Solo per pagamento completo sul prodotto principale
                    $cart_item_data['custom_price'] = $payment_amount;
                }
            } else {
                error_log('BTR Context Manager: Preservo prezzo originale per ' . 
                    ($is_insurance ? 'assicurazione' : ($is_extra ? 'extra' : 'camera')));
            }
            
            // Forza unicità per evitare raggruppamenti indesiderati
            $cart_item_data['unique_key'] = md5(json_encode(array($payment_mode, $preventivo_id, time())));
            
            error_log('BTR Context Manager: Aggiunto contesto al carrello - Mode: ' . $payment_mode . ', Preventivo: ' . $preventivo_id);
        }
        
        return $cart_item_data;
    }
    
    /**
     * Mostra contesto modalità pagamento nel carrello/checkout
     * QUESTO È QUELLO CHE L'UTENTE VEDE
     */
    public function display_payment_context_in_cart($item_data, $cart_item) {
        // Modalità di pagamento
        if (isset($cart_item[self::PAYMENT_MODE_LABEL_KEY])) {
            $item_data[] = array(
                'key'   => '<strong>🎯 ' . __('Modalità Pagamento', 'born-to-ride') . '</strong>',
                'value' => '<span class="btr-payment-mode">' . $cart_item[self::PAYMENT_MODE_LABEL_KEY] . '</span>',
                'display' => '' // Lascia vuoto per usare key/value
            );
        }
        
        // Partecipanti
        if (isset($cart_item[self::PARTICIPANTS_INFO_KEY])) {
            $participants = $cart_item[self::PARTICIPANTS_INFO_KEY];
            $item_data[] = array(
                'key'   => '<strong>👥 ' . __('Partecipanti', 'born-to-ride') . '</strong>',
                'value' => sprintf('<span class="btr-participants">%d persone (%s)</span>', 
                    $participants['total'], 
                    $participants['breakdown']
                ),
                'display' => ''
            );
        }
        
        // Importo specifico modalità
        if (isset($cart_item[self::PAYMENT_AMOUNT_KEY])) {
            $amount = $cart_item[self::PAYMENT_AMOUNT_KEY];
            $label = $cart_item[self::PAYMENT_MODE_META_KEY] === 'caparro' ? 
                __('Importo Caparra', 'born-to-ride') : 
                __('Importo da Pagare', 'born-to-ride');
                
            $item_data[] = array(
                'key'   => '<strong>💰 ' . $label . '</strong>',
                'value' => '<span class="btr-payment-amount">' . wc_price($amount) . '</span>',
                'display' => ''
            );
        }
        
        // Assegnazioni gruppo
        if (isset($cart_item[self::GROUP_ASSIGNMENTS_KEY]) && !empty($cart_item[self::GROUP_ASSIGNMENTS_KEY])) {
            $assignments = $cart_item[self::GROUP_ASSIGNMENTS_KEY];
            $assignments_text = array_map(function($assignment) {
                return sprintf('%s (%d quote)', $assignment['name'], $assignment['shares']);
            }, $assignments);
            
            $item_data[] = array(
                'key'   => '<strong>📋 ' . __('Assegnazioni Gruppo', 'born-to-ride') . '</strong>',
                'value' => '<span class="btr-group-assignments">' . implode(', ', $assignments_text) . '</span>',
                'display' => ''
            );
        }
        
        return $item_data;
    }
    
    /**
     * Display prominente nel checkout per tema classico
     */
    public function display_payment_mode_in_checkout() {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        // Trova il primo item con contesto modalità
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item[self::PAYMENT_MODE_LABEL_KEY])) {
                ?>
                <div class="btr-checkout-payment-context" style="
                    background: #fff;
                    color: inherit;
                    padding: 16px;
                    border-radius: 6px;
                    margin-bottom: 24px;
                    border: 1px solid #e5e7eb;
                    box-shadow: none;
                ">
                    <h3 style="margin-top: 0;">
                        🎯 <?php echo esc_html($cart_item[self::PAYMENT_MODE_LABEL_KEY]); ?>
                    </h3>
                    
                    <?php if (isset($cart_item[self::PARTICIPANTS_INFO_KEY])): ?>
                        <p style="margin: 10px 0;">
                            👥 <?php 
                            $info = $cart_item[self::PARTICIPANTS_INFO_KEY];
                            echo sprintf(__('%d partecipanti: %s', 'born-to-ride'), 
                                $info['total'], 
                                $info['breakdown']
                            ); 
                            ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (isset($cart_item[self::PAYMENT_AMOUNT_KEY])): ?>
                        <p style="font-size: 1.2em; font-weight: bold; margin: 10px 0;">
                            💰 <?php echo __('Importo:', 'born-to-ride') . ' ' . wc_price($cart_item[self::PAYMENT_AMOUNT_KEY]); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (isset($cart_item[self::PREVENTIVO_ID_KEY])): ?>
                        <p style="margin: 10px 0; opacity: 0.9;">
                            📋 <?php echo __('Preventivo #', 'born-to-ride') . $cart_item[self::PREVENTIVO_ID_KEY]; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php
                break; // Mostra solo una volta
            }
        }
    }
    
    /**
     * Salva contesto nell'ordine finale - PERMANENTE
     */
    public function add_payment_context_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values[self::PAYMENT_MODE_META_KEY])) {
            // Salva TUTTI i metadata nell'ordine
            $item->add_meta_data(self::PAYMENT_MODE_META_KEY, $values[self::PAYMENT_MODE_META_KEY]);
            $item->add_meta_data(self::PAYMENT_MODE_LABEL_KEY, $values[self::PAYMENT_MODE_LABEL_KEY]);
            $item->add_meta_data(self::PREVENTIVO_ID_KEY, $values[self::PREVENTIVO_ID_KEY]);
            $item->add_meta_data(self::PARTICIPANTS_INFO_KEY, $values[self::PARTICIPANTS_INFO_KEY]);
            
            if (isset($values[self::PAYMENT_AMOUNT_KEY])) {
                $item->add_meta_data(self::PAYMENT_AMOUNT_KEY, $values[self::PAYMENT_AMOUNT_KEY]);
            }
            
            if (isset($values[self::GROUP_ASSIGNMENTS_KEY])) {
                $item->add_meta_data(self::GROUP_ASSIGNMENTS_KEY, $values[self::GROUP_ASSIGNMENTS_KEY]);
            }
            
            // Aggiungi anche come order meta per facile accesso
            $order->update_meta_data('_btr_payment_mode', $values[self::PAYMENT_MODE_META_KEY]);
            $order->update_meta_data('_btr_preventivo_id', $values[self::PREVENTIVO_ID_KEY]);
            
            error_log('BTR Context Manager: Salvato contesto nell\'ordine #' . $order->get_id());
        }
    }
    
    /**
     * Store API Extension per WooCommerce Blocks
     * PER IL CHECKOUT MODERNO REACT
     */
    public function init_store_api_extension() {
        // Verifica che la funzione esista (richiede WooCommerce 6.0+)
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            error_log('BTR Context Manager: Store API non disponibile - usando fallback classico');
            return;
        }
        
        try {
            // Estendi Cart Items endpoint
            woocommerce_store_api_register_endpoint_data(array(
                'endpoint'        => 'cart-items',
                'namespace'       => 'btr-payment-context',
                'data_callback'   => function($cart_item) {
                    $data = array();

                    // DEBUG v1.0.244: Log per verificare cosa arriva alla Store API
                    error_log('BTR Store API Debug - Cart item keys: ' . print_r(array_keys($cart_item), true));

                    // Dati del contesto pagamento BTR
                    if (isset($cart_item[self::PAYMENT_MODE_META_KEY])) {
                        error_log('BTR Store API Debug - Payment context found: ' . $cart_item[self::PAYMENT_MODE_META_KEY]);
                        $data['payment_mode'] = $cart_item[self::PAYMENT_MODE_META_KEY];
                        $data['payment_mode_label'] = $cart_item[self::PAYMENT_MODE_LABEL_KEY];
                        $data['preventivo_id'] = $cart_item[self::PREVENTIVO_ID_KEY];
                        $data['participants_info'] = $cart_item[self::PARTICIPANTS_INFO_KEY];
                        $data['payment_amount'] = $cart_item[self::PAYMENT_AMOUNT_KEY] ?? null;
                        $data['group_assignments'] = $cart_item[self::GROUP_ASSIGNMENTS_KEY] ?? null;
                    } else {
                        error_log('BTR Store API Debug - No payment context found in cart item');
                    }
                    
                    // CONSOLIDATO: Dati pricing da BTR_Store_API_Integration
                    if (!empty($cart_item['custom_price'])) {
                        $data['custom_price'] = floatval($cart_item['custom_price']);
                        $data['has_custom_price'] = true;
                    }
                    
                    if (!empty($cart_item['type'])) {
                        $data['item_type'] = $cart_item['type'];
                    }
                    
                    if (!empty($cart_item['custom_name'])) {
                        $data['custom_name'] = $cart_item['custom_name'];
                    }
                    
                    if (!empty($cart_item['from_btr_detailed'])) {
                        $data['is_btr_item'] = true;
                    }
                    
                    return $data;
                },
                'schema_callback' => function() {
                    return array(
                        // Schema contesto pagamento BTR
                        'payment_mode' => array(
                            'description' => __('Modalità di pagamento', 'born-to-ride'),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                        'payment_mode_label' => array(
                            'description' => __('Etichetta modalità', 'born-to-ride'),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                        'preventivo_id' => array(
                            'description' => __('ID preventivo', 'born-to-ride'),
                            'type'        => 'integer',
                            'readonly'    => true,
                        ),
                        'participants_info' => array(
                            'description' => __('Info partecipanti', 'born-to-ride'),
                            'type'        => 'object',
                            'readonly'    => true,
                        ),
                        'payment_amount' => array(
                            'description' => __('Importo pagamento', 'born-to-ride'),
                            'type'        => 'number',
                            'readonly'    => true,
                        ),
                        'group_assignments' => array(
                            'description' => __('Assegnazioni gruppo', 'born-to-ride'),
                            'type'        => 'array',
                            'readonly'    => true,
                        ),
                        // CONSOLIDATO: Schema pricing da BTR_Store_API_Integration
                        'custom_price' => array(
                            'description' => __('Prezzo personalizzato BTR', 'born-to-ride'),
                            'type'        => 'number',
                            'readonly'    => true,
                        ),
                        'has_custom_price' => array(
                            'description' => __('Ha prezzo personalizzato', 'born-to-ride'),
                            'type'        => 'boolean',
                            'readonly'    => true,
                        ),
                        'item_type' => array(
                            'description' => __('Tipo prodotto BTR', 'born-to-ride'),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                        'custom_name' => array(
                            'description' => __('Nome personalizzato', 'born-to-ride'),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                        'is_btr_item' => array(
                            'description' => __('È prodotto BTR', 'born-to-ride'),
                            'type'        => 'boolean',
                            'readonly'    => true,
                        ),
                    );
                },
                'schema_type'     => ARRAY_A,
            ));
        } catch (Exception $e) {
            error_log('BTR Context Manager: Errore Store API Extension - ' . $e->getMessage());
        }
    }
    
    // === UTILITY METHODS ===
    
    /**
     * Recupera modalità pagamento corrente
     */
    private function get_current_payment_mode() {
        // Prima controlla POST (form submission)
        if (isset($_POST['payment_mode'])) {
            $mode = sanitize_text_field($_POST['payment_mode']);
            // Salva in sessione per persistenza
            if (WC()->session) {
                WC()->session->set('btr_payment_mode', $mode);
            }
            return $mode;
        }
        
        // Poi controlla GET (redirect da payment selection)
        if (isset($_GET['payment_mode'])) {
            $mode = sanitize_text_field($_GET['payment_mode']);
            if (WC()->session) {
                WC()->session->set('btr_payment_mode', $mode);
            }
            return $mode;
        }
        
        // Infine controlla sessione
        if (WC()->session) {
            return WC()->session->get('btr_payment_mode');
        }
        
        return null;
    }
    
    /**
     * Recupera ID preventivo corrente
     */
    private function get_current_preventivo_id() {
        // Stessa logica: POST -> GET -> Session
        if (isset($_POST['preventivo_id'])) {
            $id = intval($_POST['preventivo_id']);
            if (WC()->session) {
                WC()->session->set('btr_preventivo_id', $id);
            }
            return $id;
        }
        
        if (isset($_GET['preventivo_id'])) {
            $id = intval($_GET['preventivo_id']);
            if (WC()->session) {
                WC()->session->set('btr_preventivo_id', $id);
            }
            return $id;
        }
        
        if (WC()->session) {
            return WC()->session->get('btr_preventivo_id');
        }
        
        return null;
    }
    
    /**
     * Ottieni etichetta modalità pagamento user-friendly
     */
    private function get_payment_mode_label($mode) {
        $labels = array(
            'caparro' => __('Pagamento Caparra (30%)', 'born-to-ride'),
            'gruppo' => __('Pagamento di Gruppo', 'born-to-ride'),
            'completo' => __('Pagamento Completo', 'born-to-ride'),
            'saldo' => __('Saldo Finale', 'born-to-ride')
        );
        
        return isset($labels[$mode]) ? $labels[$mode] : ucfirst($mode);
    }
    
    /**
     * Calcola importo basato su modalità
     * CRITICO v1.0.238: Allineato con logica payment-selection-page
     * v1.0.239: PRICE SNAPSHOT INTEGRATION - Usa prezzi salvati nel snapshot immutabile
     */
    private function calculate_payment_amount($mode, $preventivo_id) {
        // PRICE SNAPSHOT SYSTEM v1.0 - Usa snapshot se disponibile per evitare ricalcoli errati
        $price_snapshot = get_post_meta($preventivo_id, '_price_snapshot', true);
        $has_snapshot = get_post_meta($preventivo_id, '_has_price_snapshot', true);
        
        if ($has_snapshot && !empty($price_snapshot) && isset($price_snapshot['totals']['grand_total'])) {
            // Usa il totale dal snapshot immutabile 
            $totale = floatval($price_snapshot['totals']['grand_total']);
            error_log('[BTR PRICE SNAPSHOT] Context Manager: Usando totale da snapshot - €' . $totale . ' (Hash: ' . substr($price_snapshot['integrity_hash'] ?? 'none', 0, 8) . ')');
            
            // Verifica integrità hash per sicurezza
            if (isset($price_snapshot['integrity_hash'])) {
                $expected_hash = hash('sha256', serialize([
                    $price_snapshot['rooms_total'] ?? 0,
                    $price_snapshot['totals']['grand_total'] ?? 0,
                    $price_snapshot['participants'] ?? [],
                    $price_snapshot['timestamp'] ?? ''
                ]));
                
                if ($expected_hash !== $price_snapshot['integrity_hash']) {
                    error_log('[BTR PRICE SNAPSHOT] ERRORE: Hash integrità non corrisponde! Possibile alterazione dati.');
                    // Fallback a metodo legacy per sicurezza
                    $totale = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
                }
            }
        } else {
            // Fallback al metodo legacy per preventivi senza snapshot
            $totale = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
            error_log('[BTR LEGACY] Context Manager: Usando totale legacy - €' . $totale . ' (preventivo senza snapshot)');
        }
        
        // Debug per verificare coerenza
        error_log('BTR Context Manager: Calcolo importo - Mode: ' . $mode . ', Preventivo: ' . $preventivo_id . ', Totale: €' . $totale);
        
        switch ($mode) {
            case 'caparro':
                // 30% del totale per caparra (come in payment-selection-page)
                $importo = round($totale * 0.3, 2);
                error_log('BTR Context Manager: Caparra 30% = €' . $importo);
                return $importo;
                
            case 'gruppo':
                // IMPORTANTE: L'organizzatore gruppo NON paga
                // Il pagamento è gestito dai singoli partecipanti
                $order_type = WC()->session ? WC()->session->get('btr_order_type', '') : '';
                
                if ($order_type === 'group_organizer') {
                    error_log('BTR Context Manager: Organizzatore gruppo - importo €0');
                    return 0;
                }
                
                // Se è un partecipante, calcola la sua quota
                $selected_shares = WC()->session ? WC()->session->get('btr_selected_shares', 0) : 0;
                if ($selected_shares > 0) {
                    $total_participants = $this->get_total_participants($preventivo_id);
                    $importo = round(($totale / $total_participants) * $selected_shares, 2);
                    error_log('BTR Context Manager: Partecipante gruppo - quote: ' . $selected_shares . '/' . $total_participants . ' = €' . $importo);
                    return $importo;
                }
                
                // Default: organizzatore paga 0
                return 0;
                
            case 'saldo':
                // Calcola saldo rimanente dopo caparra
                $pagato = floatval(get_post_meta($preventivo_id, '_amount_paid', true));
                $saldo = round(max(0, $totale - $pagato), 2);
                error_log('BTR Context Manager: Saldo = €' . $totale . ' - €' . $pagato . ' = €' . $saldo);
                return $saldo;
                
            case 'completo':
            default:
                error_log('BTR Context Manager: Pagamento completo = €' . $totale);
                return $totale;
        }
    }
    
    /**
     * Ottieni informazioni partecipanti formattate
     */
    private function get_participants_info($preventivo_id) {
        if (!$preventivo_id) {
            return array();
        }
        
        // Recupera dati dal preventivo
        $num_adults = intval(get_post_meta($preventivo_id, '_num_adults', true));
        $num_children = intval(get_post_meta($preventivo_id, '_num_children', true));
        $num_neonati = intval(get_post_meta($preventivo_id, '_num_neonati', true));
        
        $total = $num_adults + $num_children + $num_neonati;
        
        $breakdown_parts = array();
        if ($num_adults > 0) {
            $breakdown_parts[] = sprintf(_n('%d adulto', '%d adulti', $num_adults, 'born-to-ride'), $num_adults);
        }
        if ($num_children > 0) {
            $breakdown_parts[] = sprintf(_n('%d bambino', '%d bambini', $num_children, 'born-to-ride'), $num_children);
        }
        if ($num_neonati > 0) {
            $breakdown_parts[] = sprintf(_n('%d neonato', '%d neonati', $num_neonati, 'born-to-ride'), $num_neonati);
        }
        
        return array(
            'total' => $total,
            'adults' => $num_adults,
            'children' => $num_children,
            'infants' => $num_neonati,
            'breakdown' => implode(', ', $breakdown_parts)
        );
    }
    
    /**
     * Ottieni assegnazioni gruppo dalla sessione
     */
    private function get_group_assignments($preventivo_id) {
        if (!WC()->session) {
            return array();
        }
        
        $assignments = WC()->session->get('btr_group_assignments', array());
        
        // Format per display
        $formatted = array();
        foreach ($assignments as $participant_id => $data) {
            if (isset($data['selected']) && $data['selected']) {
                $formatted[] = array(
                    'id' => $participant_id,
                    'name' => $data['name'] ?? 'Partecipante',
                    'shares' => $data['shares'] ?? 1
                );
            }
        }
        
        return $formatted;
    }
    
    /**
     * Ottieni totale partecipanti
     */
    private function get_total_participants($preventivo_id) {
        $info = $this->get_participants_info($preventivo_id);
        return $info['total'] ?? 1;
    }
    
    /**
     * Imposta contesto nella sessione - METODO PUBBLICO
     */
    public function set_payment_context($payment_mode, $preventivo_id, $additional_data = array()) {
        if (!WC()->session) {
            WC()->initialize_session();
        }
        
        $session = WC()->session;
        if ($session) {
            $session->set('btr_payment_mode', $payment_mode);
            $session->set('btr_preventivo_id', $preventivo_id);
            
            // Salva dati aggiuntivi
            foreach ($additional_data as $key => $value) {
                $session->set('btr_' . $key, $value);
            }
            
            error_log('BTR Context Manager: Contesto salvato in sessione - Mode: ' . $payment_mode);
        }
    }
    
    /**
     * Pulisci contesto dalla sessione
     */
    public function clear_payment_context() {
        $session = WC()->session;
        if ($session) {
            $session->__unset('btr_payment_mode');
            $session->__unset('btr_preventivo_id');
            $session->__unset('btr_selected_shares');
            $session->__unset('btr_group_assignments');
            
            error_log('BTR Context Manager: Contesto pulito dalla sessione');
        }
    }
    
    /**
     * Enqueue CSS per styling checkout
     * @since 1.0.238
     */
    public function enqueue_checkout_styles() {
        // Solo nel checkout e nel carrello
        if (is_checkout() || is_cart()) {
            // CSS per styling
            wp_enqueue_style(
                'btr-checkout-context',
                BTR_PLUGIN_URL . 'assets/css/btr-checkout-context.css',
                array(),
                BTR_VERSION . '.300',
                'all'
            );
            
            // Nessuna personalizzazione inline: lo stile resta minimale e coerente col tema
        }
    }

    /**
     * FIX v1.0.244: Enqueue script per checkout blocks con timing corretto
     * Usa hook specifico di WooCommerce Blocks per garantire che le dipendenze siano disponibili
     *
     * @since 1.0.244
     */
    public function enqueue_checkout_blocks_scripts() {
        // Verifica che siamo nel checkout
        if (!is_checkout()) {
            return;
        }

        // Enqueue script per checkout blocks con dipendenze corrette
        wp_enqueue_script(
            'btr-checkout-blocks-payment-context',
            BTR_PLUGIN_URL . 'assets/js/btr-checkout-blocks-payment-context.js',
            array(
                'wp-element',
                'wp-blocks',
                'wc-blocks-checkout',
                'wc-blocks-data-store',
                'wc-settings',
                'wp-data',
                'wp-plugins',  // Aggiungiamo wp-plugins per registerPlugin
                'wp-i18n'
            ),
            BTR_VERSION . '.300',
            true
        );

        // Passa dati al JavaScript
        $cart_items = WC()->cart ? WC()->cart->get_cart() : array();
        $payment_context = array();

        foreach ($cart_items as $cart_item) {
            if (isset($cart_item[self::PAYMENT_MODE_META_KEY])) {
                $payment_context = array(
                    'payment_mode' => $cart_item[self::PAYMENT_MODE_META_KEY],
                    'payment_mode_label' => $cart_item[self::PAYMENT_MODE_LABEL_KEY] ?? '',
                    'preventivo_id' => $cart_item[self::PREVENTIVO_ID_KEY] ?? '',
                    'participants_info' => $cart_item[self::PARTICIPANTS_INFO_KEY] ?? '',
                    'payment_amount' => $cart_item[self::PAYMENT_AMOUNT_KEY] ?? '',
                    'group_assignments' => $cart_item[self::GROUP_ASSIGNMENTS_KEY] ?? array()
                );
                break; // Usa il primo item con contesto
            }
        }

        if (!empty($payment_context)) {
            wp_localize_script(
                'btr-checkout-blocks-payment-context',
                'btrPaymentContext',
                $payment_context
            );
        }

        // DEBUG v1.0.244: Log per verificare che lo script sia caricato con timing corretto
        error_log('BTR Checkout Blocks Scripts: Script enqueued via WooCommerce blocks hook');
    }
}

// Initialize - CRITICO: Deve essere inizializzato sempre
BTR_Checkout_Context_Manager::get_instance();
