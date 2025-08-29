<?php
/**
 * Gestione centralizzata delle rewrite rules per il plugin
 * 
 * Gestisce il flush delle rewrite rules in modo sicuro ed efficiente,
 * evitando flush continui che potrebbero impattare le performance.
 * 
 * @package BornToRideBooking
 * @since 1.0.99
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Rewrite_Rules_Manager {
    
    /**
     * Option name per tracciare la versione delle rewrite rules
     */
    const RULES_VERSION_OPTION = 'btr_rewrite_rules_version';
    
    /**
     * Versione corrente delle rewrite rules
     * Incrementare quando si modificano le rules
     */
    const CURRENT_RULES_VERSION = '1.0.99.2';
    
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
        // Hook per verificare se serve flush
        add_action('init', [$this, 'maybe_flush_rules'], 999);
        
        // Hook per activation/deactivation
        register_activation_hook(BTR_PLUGIN_FILE, [$this, 'on_activation']);
        register_deactivation_hook(BTR_PLUGIN_FILE, [$this, 'on_deactivation']);
        
        // Admin notice se flush necessario
        add_action('admin_notices', [$this, 'admin_notice_flush_needed']);
        
        // AJAX per flush manuale
        add_action('wp_ajax_btr_flush_rewrite_rules', [$this, 'ajax_flush_rules']);
    }
    
    /**
     * Verifica se è necessario fare flush delle rewrite rules
     */
    public function maybe_flush_rules() {
        $stored_version = get_option(self::RULES_VERSION_OPTION, '0');
        
        if (version_compare($stored_version, self::CURRENT_RULES_VERSION, '<')) {
            // Flush necessario
            $this->flush_rules();
        }
    }
    
    /**
     * Esegue il flush delle rewrite rules
     */
    public function flush_rules() {
        flush_rewrite_rules(false); // false = soft flush (più veloce)
        update_option(self::RULES_VERSION_OPTION, self::CURRENT_RULES_VERSION);
        
        // Log per debug
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            btr_debug_log('Rewrite rules flushed. Version: ' . self::CURRENT_RULES_VERSION);
        }
    }
    
    /**
     * On plugin activation
     */
    public function on_activation() {
        // Registra le rules prima del flush
        if (class_exists('BTR_Group_Payments')) {
            $group_payments = new BTR_Group_Payments();
            $group_payments->add_rewrite_rules();
        }
        
        // Force hard flush on activation
        flush_rewrite_rules(true);
        update_option(self::RULES_VERSION_OPTION, self::CURRENT_RULES_VERSION);
    }
    
    /**
     * On plugin deactivation
     */
    public function on_deactivation() {
        // Rimuovi le custom rules
        flush_rewrite_rules(true);
        delete_option(self::RULES_VERSION_OPTION);
    }
    
    /**
     * Mostra admin notice se flush è necessario
     */
    public function admin_notice_flush_needed() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stored_version = get_option(self::RULES_VERSION_OPTION, '0');
        
        if (version_compare($stored_version, self::CURRENT_RULES_VERSION, '<')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Born to Ride Booking:', 'born-to-ride-booking'); ?></strong>
                    <?php esc_html_e('Le rewrite rules devono essere aggiornate per il corretto funzionamento dei link di pagamento.', 'born-to-ride-booking'); ?>
                </p>
                <p>
                    <a href="#" class="button button-primary" id="btr-flush-rules-btn">
                        <?php esc_html_e('Aggiorna Rewrite Rules', 'born-to-ride-booking'); ?>
                    </a>
                    <span class="spinner" style="float: none; margin-top: 0;"></span>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#btr-flush-rules-btn').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var $spinner = $btn.next('.spinner');
                    
                    $btn.prop('disabled', true);
                    $spinner.addClass('is-active');
                    
                    $.post(ajaxurl, {
                        action: 'btr_flush_rewrite_rules',
                        nonce: '<?php echo wp_create_nonce('btr_flush_rules'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $btn.closest('.notice').fadeOut();
                            // Reload per applicare le nuove rules
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            alert(response.data || 'Errore durante l\'aggiornamento');
                            $btn.prop('disabled', false);
                            $spinner.removeClass('is-active');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * AJAX handler per flush manuale
     */
    public function ajax_flush_rules() {
        if (!check_ajax_referer('btr_flush_rules', 'nonce', false)) {
            wp_send_json_error('Nonce verification failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $this->flush_rules();
        
        wp_send_json_success([
            'message' => __('Rewrite rules aggiornate con successo.', 'born-to-ride-booking'),
            'version' => self::CURRENT_RULES_VERSION
        ]);
    }
    
    /**
     * Utility: verifica se un URL di pagamento è valido
     */
    public static function is_payment_url_valid($url) {
        $pattern = '#/pay-individual/([a-f0-9]{64})/?$#i';
        return preg_match($pattern, $url);
    }
    
    /**
     * Utility: estrae hash da URL di pagamento
     */
    public static function extract_payment_hash($url) {
        $pattern = '#/pay-individual/([a-f0-9]{64})/?$#i';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }
}

// Inizializza il manager
BTR_Rewrite_Rules_Manager::get_instance();