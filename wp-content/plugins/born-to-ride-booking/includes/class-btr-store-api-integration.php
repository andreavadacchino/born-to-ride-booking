<?php
/**
 * Integrazione con WooCommerce Store API per il checkout React/Blocks
 * 
 * Gestisce i prezzi custom per prodotti virtuali (assicurazioni, costi extra)
 * nel nuovo checkout basato su React di WooCommerce.
 * 
 * @package BornToRideBooking
 * @since 1.0.100
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

class BTR_Store_API_Integration {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Registra l'integrazione quando WooCommerce Blocks è caricato
        add_action('woocommerce_blocks_loaded', [$this, 'register_store_api_extensions']);
        
        // Filtri per gestire i prezzi nell'API Store
        add_filter('woocommerce_store_api_product_price', [$this, 'filter_store_api_product_price'], 10, 3);
        
        // Hook per sincronizzare i prezzi quando il carrello viene aggiornato via API
        add_action('woocommerce_store_api_cart_update_cart_from_request', [$this, 'sync_cart_prices_for_api'], 10, 2);
        
        // Filtro per modificare la risposta del cart item nell'API
        add_filter('woocommerce_store_api_cart_item_schema', [$this, 'extend_cart_item_schema']);
    }
    
    /**
     * Registra le estensioni per Store API
     */
    public function register_store_api_extensions() {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        
        // Registra dati custom per i cart items
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => CartItemSchema::IDENTIFIER,
            'namespace'       => 'born-to-ride-booking',
            'data_callback'   => [$this, 'extend_cart_item_data'],
            'schema_callback' => [$this, 'get_cart_item_schema'],
            'schema_type'     => ARRAY_A,
        ]);
        
        // Log per debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BTR Store API: Estensioni registrate per Cart Items');
        }
    }
    
    /**
     * Estende i dati del cart item per l'API
     */
    public function extend_cart_item_data($cart_item) {
        $data = [];
        
        // Aggiungi il prezzo custom se presente
        if (!empty($cart_item['custom_price'])) {
            $data['custom_price'] = floatval($cart_item['custom_price']);
            $data['has_custom_price'] = true;
        }
        
        // Aggiungi il tipo se presente
        if (!empty($cart_item['type'])) {
            $data['item_type'] = $cart_item['type'];
        }
        
        // Aggiungi il nome custom se presente
        if (!empty($cart_item['custom_name'])) {
            $data['custom_name'] = $cart_item['custom_name'];
        }
        
        // Flag per identificare prodotti BTR
        if (!empty($cart_item['from_btr_detailed'])) {
            $data['is_btr_item'] = true;
        }
        
        return $data;
    }
    
    /**
     * Schema per i dati custom del cart item
     */
    public function get_cart_item_schema() {
        return [
            'custom_price' => [
                'description' => __('Prezzo personalizzato per il prodotto BTR', 'born-to-ride-booking'),
                'type'        => 'number',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
            'has_custom_price' => [
                'description' => __('Indica se il prodotto ha un prezzo personalizzato', 'born-to-ride-booking'),
                'type'        => 'boolean',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
            'item_type' => [
                'description' => __('Tipo di prodotto BTR', 'born-to-ride-booking'),
                'type'        => 'string',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
            'custom_name' => [
                'description' => __('Nome personalizzato del prodotto', 'born-to-ride-booking'),
                'type'        => 'string',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
            'is_btr_item' => [
                'description' => __('Indica se è un prodotto BTR', 'born-to-ride-booking'),
                'type'        => 'boolean',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
        ];
    }
    
    /**
     * Filtra il prezzo del prodotto per l'API Store
     */
    public function filter_store_api_product_price($price, $product, $request) {
        // Se non abbiamo il carrello, ritorna il prezzo originale
        if (!WC()->cart) {
            return $price;
        }
        
        // Cerca il prodotto nel carrello
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['data']->get_id() === $product->get_id()) {
                // Se ha un prezzo custom, usalo
                if (!empty($cart_item['custom_price'])) {
                    $custom_price = floatval($cart_item['custom_price']);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('BTR Store API: Applicato custom price €' . $custom_price . ' per ' . $product->get_name());
                    }
                    
                    return $custom_price;
                }
            }
        }
        
        return $price;
    }
    
    /**
     * Sincronizza i prezzi del carrello quando viene aggiornato via API
     */
    public function sync_cart_prices_for_api($cart, $request) {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['custom_price'])) {
                $custom_price = floatval($cart_item['custom_price']);
                
                // Imposta il prezzo sul prodotto
                $cart_item['data']->set_price($custom_price);
                
                // Forza l'aggiornamento del carrello
                WC()->cart->cart_contents[$cart_item_key] = $cart_item;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('BTR Store API: Sincronizzato prezzo €' . $custom_price . ' per ' . $cart_item['data']->get_name());
                }
            }
        }
        
        // Forza il ricalcolo dei totali
        WC()->cart->calculate_totals();
    }
    
    /**
     * Estende lo schema del cart item
     */
    public function extend_cart_item_schema($schema) {
        // Aggiungi proprietà custom allo schema
        $schema['btr_custom_price'] = [
            'description' => __('Prezzo custom BTR', 'born-to-ride-booking'),
            'type'        => 'number',
            'context'     => ['view', 'edit'],
            'readonly'    => true,
        ];
        
        return $schema;
    }
}

// Inizializza
BTR_Store_API_Integration::get_instance();