<?php
/**
 * BTR Hotfix Loader
 * 
 * Carica patch temporanee per risolvere problemi urgenti
 * in attesa di aggiornamenti ufficiali del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Hotfix_Loader {
    
    public function __construct() {
        // Carica le patch solo nel frontend
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'load_patches'), 999);
        }
    }
    
    /**
     * Carica gli script di patch
     */
    public function load_patches() {
        // Verifica se siamo su una pagina che usa il booking form
        if (!$this->is_booking_page()) {
            return;
        }
        
        // Patch per il calcolo notti extra
        $this->load_extra_nights_patch();
    }
    
    /**
     * Verifica se siamo su una pagina con il form di prenotazione
     */
    private function is_booking_page() {
        // Controlla se è una pagina con lo shortcode del booking
        global $post;
        if ($post && has_shortcode($post->post_content, 'btr_booking_form')) {
            return true;
        }
        
        // Controlla classi CSS specifiche
        if (is_page()) {
            $page_classes = get_body_class();
            $booking_classes = array('page-prenotazione', 'page-booking', 'booking-form');
            
            foreach ($booking_classes as $class) {
                if (in_array($class, $page_classes)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Carica la patch per il calcolo notti extra
     */
    private function load_extra_nights_patch() {
        // Controlla la versione del plugin
        $plugin_version = defined('BTR_VERSION') ? BTR_VERSION : '1.0.0';
        
        // Patch v1: per versioni < 1.0.37 (fix fallback 2→1)
        if (version_compare($plugin_version, '1.0.37', '<')) {
            wp_enqueue_script(
                'btr-patch-extra-nights',
                BTR_PLUGIN_URL . 'includes/patches/patch-extra-nights.js',
                array('jquery', 'btr-booking-form-js'),
                '1.0.0',
                true
            );
            
            // Aggiungi un avviso nella console
            wp_add_inline_script(
                'btr-patch-extra-nights',
                'console.warn("[BTR] ⚠️ Patch v1 applicata per fix fallback notti extra. Aggiorna il plugin alla v1.0.37+ per il fix permanente.");'
            );
        }
        
        // Patch v2: per versioni < 1.0.42 (fix conteggio 3→2)
        if (version_compare($plugin_version, '1.0.42', '<')) {
            wp_enqueue_script(
                'btr-patch-extra-nights-v2',
                BTR_PLUGIN_URL . 'includes/patches/patch-extra-nights-v2.js',
                array('jquery', 'btr-booking-form-js'),
                '2.0.0',
                true
            );
            
            // Aggiungi un avviso nella console
            wp_add_inline_script(
                'btr-patch-extra-nights-v2',
                'console.warn("[BTR] ⚠️ Patch v2 applicata per fix conteggio 3→2 notti. Aggiorna il plugin alla v1.0.42+ per il fix permanente.");'
            );
        }
    }
}

// Inizializza il loader delle patch
new BTR_Hotfix_Loader();