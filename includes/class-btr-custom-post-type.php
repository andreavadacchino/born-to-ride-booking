<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impedisce l'accesso diretto
}

class BTR_Custom_Post_Type {
    public function __construct() {
        add_action( 'init', array( $this, 'register_btr_preventivi_post_type' ) );
    }

    function register_btr_preventivi_post_type() {
        $labels = [
            'name'               => __('Preventivi', 'born-to-ride-booking'),
            'singular_name'      => __('Preventivo', 'born-to-ride-booking'),
            'menu_name'          => __('Preventivi', 'born-to-ride-booking'),
            'name_admin_bar'     => __('Preventivo', 'born-to-ride-booking'),
            'add_new'            => __('Aggiungi Nuovo', 'born-to-ride-booking'),
            'add_new_item'       => __('Aggiungi Nuovo Preventivo', 'born-to-ride-booking'),
            'new_item'           => __('Nuovo Preventivo', 'born-to-ride-booking'),
            'edit_item'          => __('Modifica Preventivo', 'born-to-ride-booking'),
            'view_item'          => __('Vedi Preventivo', 'born-to-ride-booking'),
            'all_items'          => __('Tutti i Preventivi', 'born-to-ride-booking'),
            'search_items'       => __('Cerca Preventivi', 'born-to-ride-booking'),
            'parent_item_colon'  => __('Preventivi Parent:', 'born-to-ride-booking'),
            'not_found'          => __('Nessun preventivo trovato.', 'born-to-ride-booking'),
            'not_found_in_trash' => __('Nessun preventivo trovato nel cestino.', 'born-to-ride-booking')
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'btr-booking',
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'editor', 'custom-fields']
        ];

        register_post_type('btr_preventivi', $args);
    }
}

new BTR_Custom_Post_Type();