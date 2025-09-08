<?php
/**
 * Fix per i totali del checkout WooCommerce
 * 
 * Corregge il problema dove i totali finali mostrano solo €15,00
 * invece del totale corretto di €609,30
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.89
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Checkout_Totals_Fix {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook per forzare il ricalcolo dei totali
        add_action('woocommerce_cart_calculate_fees', [$this, 'force_correct_totals'], 999);
        
        // Hook per correggere i totali visualizzati
        add_filter('woocommerce_calculated_total', [$this, 'fix_calculated_total'], 999, 2);
        
        // Hook per debug
        add_action('woocommerce_before_checkout_form', [$this, 'debug_cart_totals'], 5);
        
        // Fix per i totali del checkout blocks
        add_filter('woocommerce_store_api_cart_totals', [$this, 'fix_store_api_totals'], 999, 1);
        
        // Hook aggiuntivi per garantire totali corretti
        add_action('woocommerce_after_calculate_totals', [$this, 'ensure_correct_totals'], 999);
        add_filter('woocommerce_cart_get_total', [$this, 'filter_cart_total'], 999, 1);
        add_filter('woocommerce_cart_get_subtotal', [$this, 'filter_cart_subtotal'], 999, 1);
    }
    
    /**
     * Debug dei totali del carrello
     */
    public function debug_cart_totals() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        
        error_log('[BTR Totals Fix] === DEBUG TOTALI CARRELLO ===');
        
        $totale_prodotti = 0;
        $totale_assicurazioni = 0;
        $totale_extra = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $price = $product->get_price();
            $quantity = $cart_item['quantity'];
            $line_total = $price * $quantity;
            
            if (isset($cart_item['from_assicurazione'])) {
                $totale_assicurazioni += $line_total;
                error_log('  - Assicurazione: ' . $product->get_name() . ' = €' . $line_total);
            } elseif (isset($cart_item['from_extra'])) {
                $totale_extra += $line_total;
                error_log('  - Extra: ' . $product->get_name() . ' = €' . $line_total);
            } else {
                $totale_prodotti += $line_total;
                error_log('  - Prodotto: ' . $product->get_name() . ' = €' . $line_total);
            }
        }
        
        $totale_calcolato = $totale_prodotti + $totale_assicurazioni + $totale_extra;
        
        error_log('TOTALI COMPONENTI:');
        error_log('  - Prodotti: €' . $totale_prodotti);
        error_log('  - Assicurazioni: €' . $totale_assicurazioni);
        error_log('  - Extra: €' . $totale_extra);
        error_log('  - TOTALE CALCOLATO: €' . $totale_calcolato);
        error_log('  - Totale WooCommerce: €' . $cart->get_total('raw'));
        error_log('  - Subtotale WooCommerce: €' . $cart->get_subtotal());
        error_log('=================================');
    }
    
    /**
     * Forza il ricalcolo corretto dei totali
     */
    public function force_correct_totals($cart) {
        // Forza WooCommerce a ricalcolare i totali
        $cart->calculate_totals();
    }
    
    /**
     * Corregge il totale calcolato se necessario
     */
    public function fix_calculated_total($total, $cart) {
        // Calcola manualmente il totale corretto
        $calculated_total = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $price = $product->get_price();
            $quantity = $cart_item['quantity'];
            $calculated_total += $price * $quantity;
        }
        
        // Aggiungi fees se presenti
        $fees_total = 0;
        foreach ($cart->get_fees() as $fee) {
            $fees_total += $fee->amount;
        }
        $calculated_total += $fees_total;
        
        // Se c'è una discrepanza significativa, usa il totale calcolato
        if (abs($total - $calculated_total) > 0.01) {
            error_log('[BTR Totals Fix] Correzione totale: da €' . $total . ' a €' . $calculated_total);
            return $calculated_total;
        }
        
        return $total;
    }
    
    /**
     * Fix per i totali dell'API Store (Checkout Blocks)
     */
    public function fix_store_api_totals($totals) {
        $cart = WC()->cart;
        if (!$cart) {
            return $totals;
        }
        
        // Ricalcola il totale correttamente
        $calculated_total = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $price = $product->get_price();
            $quantity = $cart_item['quantity'];
            $calculated_total += $price * $quantity;
        }
        
        // Aggiungi fees
        foreach ($cart->get_fees() as $fee) {
            $calculated_total += $fee->amount;
        }
        
        // Converti in centesimi per l'API
        $total_in_cents = round($calculated_total * 100);
        
        // Aggiorna i totali se necessario
        if (isset($totals['total_price']) && $totals['total_price'] != $total_in_cents) {
            error_log('[BTR Totals Fix] Correzione totale API: da ' . $totals['total_price'] . ' a ' . $total_in_cents . ' centesimi');
            $totals['total_price'] = $total_in_cents;
            $totals['total_price_tax_incl'] = $total_in_cents;
        }
        
        return $totals;
    }
    
    /**
     * Garantisce che i totali siano corretti dopo il calcolo
     */
    public function ensure_correct_totals($cart) {
        // Nota: WC_Cart non ha un metodo set_total pubblico
        // Quindi questo metodo è lasciato vuoto per compatibilità futura
        return;
    }
    
    /**
     * Filtra il totale del carrello per garantire che sia corretto
     */
    public function filter_cart_total($total) {
        $cart = WC()->cart;
        if (!$cart) {
            return $total;
        }
        
        $calculated_total = $this->calculate_correct_total($cart);
        
        // Se c'è una discrepanza significativa, usa il totale calcolato
        if (abs($total - $calculated_total) > 0.01) {
            error_log('[BTR Totals Fix] Filter cart total: da €' . $total . ' a €' . $calculated_total);
            return $calculated_total;
        }
        
        return $total;
    }
    
    /**
     * Filtra il subtotale del carrello
     */
    public function filter_cart_subtotal($subtotal) {
        $cart = WC()->cart;
        if (!$cart) {
            return $subtotal;
        }
        
        $calculated_subtotal = 0;
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $price = $product->get_price();
            $quantity = $cart_item['quantity'];
            $calculated_subtotal += $price * $quantity;
        }
        
        // Se c'è una discrepanza significativa, usa il subtotale calcolato
        if (abs($subtotal - $calculated_subtotal) > 0.01) {
            error_log('[BTR Totals Fix] Filter cart subtotal: da €' . $subtotal . ' a €' . $calculated_subtotal);
            return $calculated_subtotal;
        }
        
        return $subtotal;
    }
    
    /**
     * Calcola il totale corretto del carrello
     */
    private function calculate_correct_total($cart) {
        $total = 0;
        
        // Somma tutti gli elementi del carrello
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $price = $product->get_price();
            $quantity = $cart_item['quantity'];
            $total += $price * $quantity;
        }
        
        // Aggiungi fees
        foreach ($cart->get_fees() as $fee) {
            $total += $fee->amount;
        }
        
        // Sottrai sconti
        if (method_exists($cart, 'get_discount_total')) {
            $total -= $cart->get_discount_total();
        }
        
        return $total;
    }
}

// Inizializza la classe
new BTR_Checkout_Totals_Fix();