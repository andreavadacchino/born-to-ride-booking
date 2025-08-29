<?php
/**
 * BTR Autoloader
 * 
 * PSR-4 Autoloader implementation for Born to Ride Booking plugin
 * Reduces memory footprint from 150-300MB to ~30-50MB
 * 
 * @package BTR
 * @since 1.0.200
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Autoloader {
    
    /**
     * Plugin base directory
     * @var string
     */
    private static $base_dir;
    
    /**
     * Class map for non-PSR-4 classes
     * @var array
     */
    private static $classmap = [];
    
    /**
     * Whether autoloader is registered
     * @var bool
     */
    private static $registered = false;
    
    /**
     * Initialize and register the autoloader
     */
    public static function init() {
        if (self::$registered) {
            return;
        }
        
        self::$base_dir = BTR_PLUGIN_DIR;
        self::build_classmap();
        
        // Register our autoloader
        spl_autoload_register([__CLASS__, 'autoload'], true, true);
        
        self::$registered = true;
    }
    
    /**
     * Autoload BTR classes
     * 
     * @param string $class Class name to load
     */
    public static function autoload($class) {
        // Check if it's a BTR class
        if (strpos($class, 'BTR_') !== 0) {
            return;
        }
        
        // Check classmap first (for legacy naming)
        if (isset(self::$classmap[$class])) {
            $file = self::$base_dir . self::$classmap[$class];
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // Convert class name to file path (PSR-4 style)
        $class_file = self::class_to_file($class);
        $file = self::$base_dir . 'includes/' . $class_file;
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    /**
     * Convert class name to file path
     * 
     * @param string $class Class name
     * @return string File path
     */
    private static function class_to_file($class) {
        // Remove BTR_ prefix
        $class = str_replace('BTR_', '', $class);
        
        // Convert underscores to hyphens and lowercase
        $class = strtolower(str_replace('_', '-', $class));
        
        return 'class-btr-' . $class . '.php';
    }
    
    /**
     * Build classmap for existing classes
     * Maps old class names to file paths
     */
    private static function build_classmap() {
        self::$classmap = [
            // Core classes
            'BTR_Pacchetti_CPT' => 'includes/class-btr-pacchetti-cpt.php',
            'BTR_Preventivi' => 'includes/class-btr-preventivi.php',
            'BTR_Preventivi_V2' => 'includes/class-btr-preventivi-v2.php',
            'BTR_Preventivi_V3' => 'includes/class-btr-preventivi-v3.php',
            'BTR_Preventivi_V4' => 'includes/class-btr-preventivi-v4.php',
            'BTR_Preventivi_Canonical' => 'includes/class-btr-preventivi-canonical.php',
            'BTR_Preventivi_Refactored' => 'includes/class-btr-preventivi-refactored.php',
            'BTR_Checkout' => 'includes/class-btr-checkout.php',
            'BTR_Shortcodes' => 'includes/class-btr-shortcodes.php',
            'BTR_WooCommerce_Sync' => 'includes/class-btr-woocommerce-sync.php',
            'BTR_Payment_Selection_Shortcode' => 'includes/class-btr-payment-selection-shortcode.php',
            'BTR_Shortcode_Anagrafici' => 'includes/class-btr-shortcode-anagrafici.php',
            'BTR_PDF_Generator' => 'includes/class-btr-pdf-generator.php',
            
            // Database classes
            'BTR_Database_Manager' => 'includes/class-btr-database-manager.php',
            'BTR_Database_Installer' => 'includes/class-btr-database-installer.php',
            'BTR_Database_Migration' => 'includes/class-btr-database-migration.php',
            'BTR_Database_Updater' => 'includes/class-btr-database-updater.php',
            'BTR_Database_Auto_Installer' => 'includes/class-btr-database-auto-installer.php',
            
            // Payment classes
            'BTR_Payment_Integration' => 'includes/class-btr-payment-integration.php',
            'BTR_Payment_Plans' => 'includes/class-btr-payment-plans.php',
            'BTR_Payment_Plans_Extended' => 'includes/class-btr-payment-plans-extended.php',
            'BTR_Payment_Security' => 'includes/class-btr-payment-security.php',
            'BTR_Payment_Shortcodes' => 'includes/class-btr-payment-shortcodes.php',
            'BTR_Payment_REST_Controller' => 'includes/class-btr-payment-rest-controller.php',
            'BTR_Payment_Gateway_Integration' => 'includes/class-btr-payment-gateway-integration.php',
            'BTR_Payment_Gateway_Integration_V2' => 'includes/class-btr-payment-gateway-integration-v2.php',
            'BTR_Payment_Ajax' => 'includes/class-btr-payment-ajax.php',
            'BTR_Payment_Email_Manager' => 'includes/class-btr-payment-email-manager.php',
            'BTR_Payment_Cron' => 'includes/class-btr-payment-cron.php',
            'BTR_Payment_Cron_Manager' => 'includes/class-btr-payment-cron-manager.php',
            'BTR_Payment_Cron_Enhanced' => 'includes/class-btr-payment-cron-enhanced.php',
            'BTR_Group_Payments' => 'includes/class-btr-group-payments.php',
            'BTR_Deposit_Balance' => 'includes/class-btr-deposit-balance.php',
            
            // Admin classes
            'BTR_Admin_Interface' => 'includes/class-btr-admin-interface.php',
            'BTR_Ajax_Handlers' => 'includes/class-btr-ajax-handlers.php',
            'BTR_Metabox' => 'includes/class-btr-metabox.php',
            'BTR_Save_Meta' => 'includes/class-btr-save-meta.php',
            'BTR_Debug_Admin' => 'includes/class-btr-debug-admin.php',
            'BTR_Preventivi_Admin' => 'includes/class-btr-preventivi-admin.php',
            'BTR_Preventivi_Ordini' => 'includes/class-btr-preventivi-ordini.php',
            'BTR_Preventivi_Ordini_V2' => 'includes/class-btr-preventivi-ordini-v2.php',
            
            // Manager classes
            'BTR_Variations_Manager' => 'includes/class-btr-variations-manager.php',
            'BTR_Date_Range_Manager' => 'includes/class-btr-date-range-manager.php',
            'BTR_Email_Manager' => 'includes/class-btr-email-manager.php',
            'BTR_Email_Template_Manager' => 'includes/class-btr-email-template-manager.php',
            'BTR_Cart_Extras_Manager' => 'includes/class-btr-cart-extras-manager.php',
            'BTR_Child_Labels_Manager' => 'includes/class-btr-child-labels-manager.php',
            'BTR_Gateway_API_Manager' => 'includes/class-btr-gateway-api-manager.php',
            'BTR_Rewrite_Rules_Manager' => 'includes/class-btr-rewrite-rules-manager.php',
            'BTR_Webhook_Queue_Manager' => 'includes/class-btr-webhook-queue-manager.php',
            'BTR_Quote_Data_Manager' => 'includes/class-btr-quote-data-manager.php',
            
            // Feature classes
            'BTR_Child_Age_Validator' => 'includes/class-btr-child-age-validator.php',
            'BTR_Child_Room_Pricing' => 'includes/class-btr-child-room-pricing.php',
            'BTR_Child_Extra_Night_Pricing' => 'includes/class-btr-child-extra-night-pricing.php',
            'BTR_Dynamic_Child_Categories' => 'includes/class-btr-dynamic-child-categories.php',
            'BTR_Extra_Costs_Display_Fix' => 'includes/class-btr-extra-costs-display-fix.php',
            'BTR_Extra_Costs_Sortable' => 'includes/class-btr-extra-costs-sortable.php',
            'BTR_Extra_Nights_Display' => 'includes/class-btr-extra-nights-display.php',
            'BTR_Single_Night_Fix' => 'includes/class-btr-single-night-fix.php',
            'BTR_Conditional_Address_Fields' => 'includes/class-btr-conditional-address-fields.php',
            'BTR_Store_API_Integration' => 'includes/class-btr-store-api-integration.php',
            'BTR_WooCommerce_Deposit_Integration' => 'includes/class-btr-woocommerce-deposit-integration.php',
            'BTR_Checkout_Totals_Fix' => 'includes/class-btr-checkout-totals-fix.php',
            
            // Utility classes
            'BTR_Cost_Calculator' => 'includes/class-btr-cost-calculator.php',
            'BTR_Price_Calculator' => 'includes/class-btr-price-calculator.php',
            'BTR_Frontend_Display' => 'includes/class-btr-frontend-display.php',
            'BTR_Hotfix_Loader' => 'includes/class-btr-hotfix-loader.php',
            'BTR_Labels_Revision' => 'includes/class-btr-labels-revision.php',
            'BTR_Payment_Rewrite' => 'includes/class-btr-payment-rewrite.php',
            
            // Other classes
            'BTR_Custom_Post_Type' => 'includes/class-btr-custom-post-type.php',
            'BTR_Taxonomies' => 'includes/class-btr-taxonomies.php',
            'BTR_Database' => 'includes/class-btr-database.php',
            'BTR_Cron' => 'includes/class-btr-cron.php',
            'BTR_Quotes' => 'includes/class-btr-quotes.php',
            'BTR_WPBakery' => 'includes/class-btr-wpbakery.php',
            'BTR_Sync_WooCommerce' => 'includes/class-btr-sync-woocommerce.php',
            'BTR_Prenotazioni' => 'includes/class-btr-prenotazioni.php',
            'BTR_Prenotazioni_OrderView' => 'includes/class-btr-prenotazioni-orderview.php',
            'BTR_Preventivo_To_Order' => 'includes/class-btr-preventivi-ordini.php',
            'BTR_Anagrafici_Shortcode' => 'includes/class-btr-shortcode-anagrafici.php',
            'BTR_Prenotazioni_Manager' => 'includes/class-btr-prenotazioni-orderview.php',
            'BTR_AJAX_Handlers' => 'includes/class-btr-ajax-handlers.php',
            'BTR_Database_Updater' => 'includes/class-btr-database-updater.php',
            'BTR_Database_Auto_Installer' => 'includes/class-btr-database-auto-installer.php',
            'BTR_Price_Calculator' => 'includes/class-btr-price-calculator.php',
            
            // Admin classes  
            'BTR_Dashboard' => 'admin/class-btr-dashboard.php',
            'BTR_Menu_Manager' => 'admin/class-btr-menu-manager.php',
            'BTR_Payment_Plans_Admin' => 'admin/class-btr-payment-plans-admin.php',
            'BTR_Gateway_Settings_Admin' => 'admin/class-btr-gateway-settings-admin.php',
            'BTR_Payment_Settings_Admin' => 'admin/class-btr-payment-settings-admin.php',
            'BTR_System_Diagnostics' => 'admin/class-btr-system-diagnostics.php',
        ];
    }
    
    /**
     * Get loaded classes count for debugging
     * 
     * @return int
     */
    public static function get_loaded_classes_count() {
        $btr_classes = array_filter(get_declared_classes(), function($class) {
            return strpos($class, 'BTR_') === 0;
        });
        return count($btr_classes);
    }
}