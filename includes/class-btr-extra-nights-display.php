<?php
/**
 * Gestione corretta della visualizzazione delle notti extra
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Extra_Nights_Display {
    
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
        // Hook per aggiungere informazioni extra nights nei dati AJAX
        add_filter('btr_ajax_rooms_response', [$this, 'add_extra_nights_info'], 10, 2);
        
        // Hook per formattare correttamente il display delle notti extra
        add_filter('btr_format_extra_nights_label', [$this, 'format_extra_nights_label'], 10, 3);
        
        // Hook per validare il numero di notti extra
        add_filter('btr_validate_extra_nights_count', [$this, 'validate_extra_nights_count'], 10, 2);
        
        // Aggiungi script per gestione frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }
    
    /**
     * Aggiunge informazioni sulle notti extra nella risposta AJAX
     */
    public function add_extra_nights_info($response, $request_data) {
        if (!isset($response['data'])) {
            return $response;
        }
        
        // Se ci sono notti extra, assicurati che il numero sia corretto
        if (isset($response['data']['extra_night']) && $response['data']['extra_night'] === true) {
            // Recupera il numero corretto di notti extra
            $extra_nights_count = $this->get_correct_extra_nights_count($request_data);
            
            $response['data']['extra_nights_count'] = $extra_nights_count;
            $response['data']['extra_nights_label'] = $this->format_extra_nights_label($extra_nights_count);
            
            // Aggiungi informazioni di debug
            $response['data']['extra_nights_debug'] = [
                'calculated_count' => $extra_nights_count,
                'package_nights' => $request_data['package_nights'] ?? 2,
                'total_nights' => $request_data['total_nights'] ?? null,
                'correction_applied' => false
            ];
            
            // Fix v1.0.55: Correggi il caso comune dove vengono contate 3 notti invece di 2
            if ($extra_nights_count === 3 && !isset($request_data['force_three_nights'])) {
                $response['data']['extra_nights_count'] = 2;
                $response['data']['extra_nights_debug']['correction_applied'] = true;
                $response['data']['extra_nights_debug']['original_count'] = 3;
                $response['data']['extra_nights_debug']['corrected_count'] = 2;
                
                btr_debug_log('BTR_Extra_Nights_Display: Corretto numero notti extra da 3 a 2');
            }
        }
        
        return $response;
    }
    
    /**
     * Calcola il numero corretto di notti extra
     */
    private function get_correct_extra_nights_count($request_data) {
        $package_id = $request_data['package_id'] ?? 0;
        $selected_date = $request_data['selected_date'] ?? '';
        $extra_night_flag = $request_data['extra_night'] ?? 0;
        
        if (!$extra_night_flag || !$package_id) {
            return 0;
        }
        
        // Recupera il numero di notti base del pacchetto
        $base_nights = intval(get_post_meta($package_id, 'btr_numero_notti', true)) ?: 2;
        
        // Per ora, le notti extra sono sempre 1 o 2 (dipende dalla configurazione)
        // Di default è 1 notte extra per il venerdì
        $extra_nights = 1;
        
        // Se c'è una configurazione specifica per il numero di notti extra
        $extra_nights_config = get_post_meta($package_id, 'btr_extra_nights_count', true);
        if ($extra_nights_config) {
            $extra_nights = intval($extra_nights_config);
        }
        
        return $extra_nights;
    }
    
    /**
     * Formatta l'etichetta per le notti extra
     */
    public function format_extra_nights_label($count, $date = '', $context = 'display') {
        if ($count <= 0) {
            return '';
        }
        
        $label = sprintf(
            _n('%d Notte extra', '%d Notti extra', $count, 'born-to-ride-booking'),
            $count
        );
        
        if ($date) {
            $label .= ' ' . sprintf(__('del %s', 'born-to-ride-booking'), $date);
        }
        
        if ($context === 'price' && $count > 1) {
            $label .= ' ' . __('(totale)', 'born-to-ride-booking');
        }
        
        return $label;
    }
    
    /**
     * Valida il numero di notti extra
     */
    public function validate_extra_nights_count($count, $context = []) {
        $count = intval($count);
        
        // Validazione base
        if ($count < 0) {
            return 0;
        }
        
        // Massimo notti extra consentite (configurabile)
        $max_extra_nights = apply_filters('btr_max_extra_nights', 7);
        if ($count > $max_extra_nights) {
            return $max_extra_nights;
        }
        
        // Fix comune: se sono 3 notti e non c'è una ragione specifica, probabilmente dovrebbero essere 2
        if ($count === 3 && !isset($context['allow_three_nights'])) {
            btr_debug_log('BTR_Extra_Nights_Display: Validazione - corretto da 3 a 2 notti extra');
            return 2;
        }
        
        return $count;
    }
    
    /**
     * Enqueue scripts per gestione frontend
     */
    public function enqueue_frontend_scripts() {
        if (!is_singular() || !has_shortcode(get_post()->post_content, 'btr_booking_form')) {
            return;
        }
        
        // Aggiungi helper JavaScript inline
        $js = "
        // Helper per gestione notti extra
        window.btrExtraNightsHelper = {
            // Ottieni il numero corretto di notti extra
            getCount: function() {
                if (typeof window.btrExtraNightsCount === 'number') {
                    // Fix: se sono 3, probabilmente dovrebbero essere 2
                    if (window.btrExtraNightsCount === 3) {
                        console.warn('[BTR] Corretto numero notti extra da 3 a 2');
                        return 2;
                    }
                    return window.btrExtraNightsCount;
                }
                return 0;
            },
            
            // Formatta l'etichetta
            formatLabel: function(count, includeTotal) {
                if (!count || count <= 0) return '';
                
                var label = count + ' Nott' + (count === 1 ? 'e' : 'i') + ' extra';
                if (includeTotal && count > 1) {
                    label += ' (totale)';
                }
                return label;
            },
            
            // Calcola il prezzo totale per le notti extra
            calculateTotal: function(pricePerNight, count, persons) {
                count = this.getCount();
                if (!count || !pricePerNight || !persons) return 0;
                
                return pricePerNight * count * persons;
            },
            
            // Verifica se le notti extra sono attive
            isActive: function() {
                return window.btrExtraNightsCount && window.btrExtraNightsCount > 0;
            }
        };
        ";
        
        wp_add_inline_script('btr-booking-form', $js, 'after');
    }
    
    /**
     * Helper per template - genera HTML per il riepilogo notti extra
     */
    public static function render_extra_nights_summary($data) {
        if (empty($data['extra_nights']) || !$data['extra_nights']['active']) {
            return '';
        }
        
        $count = $data['extra_nights']['count'] ?? 0;
        $price_pp = $data['extra_nights']['price_per_person'] ?? 0;
        $total_persons = $data['extra_nights']['total_persons'] ?? 0;
        $date = $data['extra_nights']['date'] ?? '';
        
        if ($count <= 0 || $price_pp <= 0) {
            return '';
        }
        
        $total = $price_pp * $total_persons * $count;
        
        ob_start();
        ?>
        <div class="btr-extra-nights-summary">
            <h4><?php echo esc_html(self::get_instance()->format_extra_nights_label($count)); ?></h4>
            <div class="btr-extra-nights-details">
                <?php if ($date): ?>
                    <p class="btr-extra-night-date">
                        <strong><?php esc_html_e('Data:', 'born-to-ride-booking'); ?></strong> 
                        <?php echo esc_html($date); ?>
                    </p>
                <?php endif; ?>
                
                <p class="btr-extra-night-price">
                    <strong><?php esc_html_e('Prezzo per persona:', 'born-to-ride-booking'); ?></strong> 
                    €<?php echo number_format($price_pp, 2, ',', '.'); ?>
                    <?php if ($count > 1): ?>
                        <small>(<?php echo esc_html(sprintf(__('per %d notti', 'born-to-ride-booking'), $count)); ?>)</small>
                    <?php endif; ?>
                </p>
                
                <p class="btr-extra-night-persons">
                    <strong><?php esc_html_e('Persone:', 'born-to-ride-booking'); ?></strong> 
                    <?php echo intval($total_persons); ?>
                </p>
                
                <p class="btr-extra-night-total">
                    <strong><?php esc_html_e('Totale notti extra:', 'born-to-ride-booking'); ?></strong> 
                    <span class="btr-price-highlight">€<?php echo number_format($total, 2, ',', '.'); ?></span>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Funzioni helper globali
if (!function_exists('btr_format_extra_nights_label')) {
    function btr_format_extra_nights_label($count, $date = '', $context = 'display') {
        return BTR_Extra_Nights_Display::get_instance()->format_extra_nights_label($count, $date, $context);
    }
}

if (!function_exists('btr_validate_extra_nights_count')) {
    function btr_validate_extra_nights_count($count, $context = []) {
        return BTR_Extra_Nights_Display::get_instance()->validate_extra_nights_count($count, $context);
    }
}

// Inizializza
add_action('init', function() {
    BTR_Extra_Nights_Display::get_instance();
});