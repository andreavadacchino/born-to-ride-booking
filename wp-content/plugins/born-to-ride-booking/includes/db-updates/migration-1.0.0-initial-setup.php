<?php
/**
 * Migration 1.0.0 - Initial Setup
 * 
 * Setup iniziale del database per Born to Ride Booking
 * 
 * @package BornToRideBooking
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BTR_Migration_1_0_0
 */
class BTR_Migration_1_0_0 {
    
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;
        
        // Questa è una migration placeholder per il setup iniziale
        // Le tabelle principali sono già gestite dal plugin esistente
        
        // Aggiungi opzioni di base se non esistono
        if (!get_option('btr_plugin_installed')) {
            add_option('btr_plugin_installed', date('Y-m-d H:i:s'));
        }
        
        // Aggiungi capabilities personalizzate
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_btr_bookings');
            $role->add_cap('edit_btr_packages');
        }
        
        btr_debug_log('Migration 1.0.0 completed - Initial setup');
    }
    
    /**
     * Rollback the migration
     */
    public function down() {
        // Rimuovi capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_btr_bookings');
            $role->remove_cap('edit_btr_packages');
        }
        
        // Non rimuoviamo l'opzione installed per sicurezza
        
        btr_debug_log('Migration 1.0.0 rolled back');
    }
}