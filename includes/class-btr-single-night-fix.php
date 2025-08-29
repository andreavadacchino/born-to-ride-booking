<?php
/**
 * Fix per la visualizzazione corretta dei prezzi quando il pacchetto ha 1 sola notte
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Single_Night_Fix {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
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
    private function __construct() {
        // Hook per modificare i calcoli quando c'è 1 sola notte
        add_filter('btr_calculate_package_nights', [$this, 'get_package_nights'], 10, 2);
        add_filter('btr_price_calculation_params', [$this, 'adjust_calculation_params'], 10, 2);
        
        // Hook per aggiustare il display nel frontend
        add_filter('btr_frontend_nights_label', [$this, 'format_nights_label'], 10, 2);
        
        // Aggiungi informazioni sul numero di notti nei dati AJAX
        add_filter('btr_ajax_rooms_response', [$this, 'add_nights_info'], 10, 2);
        
        // Script per il frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_fix']);
    }
    
    /**
     * Ottieni il numero corretto di notti per il pacchetto
     */
    public function get_package_nights($nights, $package_id) {
        if (!$package_id) {
            return $nights;
        }
        
        // Recupera il numero di notti dal meta del pacchetto
        $stored_nights = get_post_meta($package_id, 'btr_numero_notti', true);
        
        if ($stored_nights !== false && $stored_nights !== '') {
            $nights = max(1, intval($stored_nights));
            btr_debug_log('BTR_Single_Night_Fix: Numero notti per pacchetto #' . $package_id . ': ' . $nights);
        }
        
        return $nights;
    }
    
    /**
     * Aggiusta i parametri di calcolo per pacchetti con 1 notte
     */
    public function adjust_calculation_params($params, $context) {
        if (!isset($params['package_id'])) {
            return $params;
        }
        
        $nights = $this->get_package_nights(2, $params['package_id']);
        
        // Se il pacchetto ha 1 sola notte, aggiusta i calcoli
        if ($nights === 1) {
            // Assicurati che il supplemento venga calcolato per 1 notte sola
            $params['base_nights'] = 1;
            
            // Se ci sono supplementi per notte, assicurati che siano calcolati correttamente
            if (isset($params['supplemento_per_notte'])) {
                $params['supplemento_totale'] = $params['supplemento_per_notte'] * 1;
            }
            
            btr_debug_log('BTR_Single_Night_Fix: Parametri aggiustati per pacchetto 1 notte');
        }
        
        return $params;
    }
    
    /**
     * Formatta l'etichetta per il numero di notti
     */
    public function format_nights_label($label, $nights) {
        if ($nights === 1) {
            return __('1 notte', 'born-to-ride-booking');
        }
        
        return sprintf(_n('%d notte', '%d notti', $nights, 'born-to-ride-booking'), $nights);
    }
    
    /**
     * Aggiunge informazioni sul numero di notti nella risposta AJAX
     */
    public function add_nights_info($response, $request_data) {
        if (!isset($response['data']) || !isset($request_data['package_id'])) {
            return $response;
        }
        
        $package_id = $request_data['package_id'];
        $nights = $this->get_package_nights(2, $package_id);
        
        $response['data']['package_nights'] = $nights;
        $response['data']['package_nights_label'] = $this->format_nights_label('', $nights);
        
        // Aggiungi flag specifico per pacchetti 1 notte
        if ($nights === 1) {
            $response['data']['is_single_night_package'] = true;
        }
        
        return $response;
    }
    
    /**
     * Enqueue script di fix per il frontend
     */
    public function enqueue_frontend_fix() {
        if (!is_singular() || !has_shortcode(get_post()->post_content, 'btr_booking_form')) {
            return;
        }
        
        // JavaScript inline per gestire pacchetti 1 notte
        $js = "
        // Fix per pacchetti con 1 sola notte
        (function($) {
            // Hook nella risposta AJAX delle camere
            $(document).on('btr:rooms_loaded', function(event, response) {
                if (response.data && response.data.is_single_night_package) {
                    console.log('[BTR] Rilevato pacchetto 1 notte - applicazione fix prezzi');
                    
                    // Aggiorna la variabile globale per i calcoli
                    window.btrPackageNights = 1;
                    
                    // Sostituisci le occorrenze di '2 notti' con '1 notte' nel riepilogo
                    $('.btr-breakdown-summary').each(function() {
                        var html = $(this).html();
                        if (html) {
                            // Pattern per trovare '2 notti' nel contesto dei supplementi
                            html = html.replace(/2\\s+notti?/gi, '1 notte');
                            html = html.replace(/× 2 \\(notti\\)/gi, '× 1 (notte)');
                            $(this).html(html);
                        }
                    });
                    
                    // Aggiorna anche i calcoli JavaScript
                    if (typeof window.updatePackageNightsCalculation === 'function') {
                        window.updatePackageNightsCalculation(1);
                    }
                }
            });
            
            // Override della costante basePackageNights nei calcoli
            var originalCalculatePrice = window.calculateRoomPrice;
            if (typeof originalCalculatePrice === 'function') {
                window.calculateRoomPrice = function() {
                    // Temporaneamente sostituisci il valore
                    var originalNights = window.basePackageNights;
                    if (window.btrPackageNights === 1) {
                        window.basePackageNights = 1;
                    }
                    
                    var result = originalCalculatePrice.apply(this, arguments);
                    
                    // Ripristina il valore originale
                    window.basePackageNights = originalNights;
                    
                    return result;
                };
            }
            
            // Fix per la visualizzazione nel breakdown dettagliato
            $(document).on('btr:breakdown_generated', function(event, breakdown) {
                if (window.btrPackageNights === 1 && breakdown) {
                    // Aggiorna le etichette nel breakdown
                    $('.btr-breakdown-nights').each(function() {
                        $(this).text('1 notte');
                    });
                    
                    // Aggiorna i calcoli dei supplementi
                    $('.btr-supplement-calculation').each(function() {
                        var text = $(this).text();
                        if (text.includes('× 2 notti')) {
                            $(this).text(text.replace('× 2 notti', '× 1 notte'));
                        }
                    });
                }
            });
        })(jQuery);
        
        // Helper globale per ottenere il numero di notti del pacchetto
        window.getBtrPackageNights = function() {
            return window.btrPackageNights || 2; // Default a 2 se non specificato
        };
        ";
        
        wp_add_inline_script('btr-booking-form', $js, 'after');
    }
}

// Funzione helper globale
if (!function_exists('btr_get_package_nights')) {
    function btr_get_package_nights($package_id) {
        return apply_filters('btr_calculate_package_nights', 2, $package_id);
    }
}

// Inizializza
add_action('init', function() {
    BTR_Single_Night_Fix::get_instance();
});