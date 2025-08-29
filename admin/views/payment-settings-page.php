<?php
/**
 * Aggiunge impostazioni per la pagina di selezione pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Hook per aggiungere la sezione nelle impostazioni
add_action('admin_init', function() {
    // Aggiungi sezione nelle impostazioni generali del plugin
    add_settings_section(
        'btr_payment_pages_section',
        __('Pagine Sistema Pagamenti', 'born-to-ride-booking'),
        'btr_payment_pages_section_callback',
        'btr-settings'
    );
    
    // Campo per la pagina di selezione pagamento
    add_settings_field(
        'btr_payment_selection_page_id',
        __('Pagina Selezione Piano Pagamento', 'born-to-ride-booking'),
        'btr_payment_selection_page_field_callback',
        'btr-settings',
        'btr_payment_pages_section'
    );
    
    // Registra l'opzione
    register_setting('btr-settings-group', 'btr_payment_selection_page_id');
});

/**
 * Callback per la descrizione della sezione
 */
function btr_payment_pages_section_callback() {
    echo '<p>' . __('Configura le pagine utilizzate dal sistema di pagamenti.', 'born-to-ride-booking') . '</p>';
}

/**
 * Callback per il campo pagina selezione
 */
function btr_payment_selection_page_field_callback() {
    $selected_page = get_option('btr_payment_selection_page_id');
    
    // Dropdown delle pagine
    wp_dropdown_pages([
        'name' => 'btr_payment_selection_page_id',
        'echo' => 1,
        'show_option_none' => __('— Seleziona —', 'born-to-ride-booking'),
        'option_none_value' => '0',
        'selected' => $selected_page
    ]);
    
    echo '<p class="description">' . __('Seleziona la pagina che contiene lo shortcode [btr_payment_selection]', 'born-to-ride-booking') . '</p>';
    
    // Se non c'è una pagina selezionata, mostra il pulsante per crearla
    if (!$selected_page) {
        ?>
        <p>
            <button type="button" class="button" id="btr-create-payment-page">
                <?php _e('Crea Pagina Automaticamente', 'born-to-ride-booking'); ?>
            </button>
        </p>
        
        <script>
        jQuery(document).ready(function($) {
            $('#btr-create-payment-page').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php _e('Creazione in corso...', 'born-to-ride-booking'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'btr_create_payment_selection_page',
                        nonce: '<?php echo wp_create_nonce('btr_create_page_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Pagina creata con successo!', 'born-to-ride-booking'); ?>');
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Errore nella creazione della pagina', 'born-to-ride-booking'); ?>');
                            $button.prop('disabled', false).text('<?php _e('Crea Pagina Automaticamente', 'born-to-ride-booking'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Errore di connessione', 'born-to-ride-booking'); ?>');
                        $button.prop('disabled', false).text('<?php _e('Crea Pagina Automaticamente', 'born-to-ride-booking'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Hook AJAX per creare la pagina
add_action('wp_ajax_btr_create_payment_selection_page', function() {
    // Verifica nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_create_page_nonce')) {
        wp_send_json_error(['message' => __('Errore di sicurezza', 'born-to-ride-booking')]);
    }
    
    // Verifica permessi
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Non autorizzato', 'born-to-ride-booking')]);
    }
    
    // Verifica se la pagina esiste già
    $existing_page = get_page_by_path('selezione-piano-pagamento');
    
    if ($existing_page) {
        // Usa la pagina esistente
        update_option('btr_payment_selection_page_id', $existing_page->ID);
        wp_send_json_success(['page_id' => $existing_page->ID]);
    }
    
    // Crea la nuova pagina
    $page_data = [
        'post_title'    => __('Selezione Piano Pagamento', 'born-to-ride-booking'),
        'post_content'  => '[btr_payment_selection]',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_name'     => 'selezione-piano-pagamento',
        'post_author'   => get_current_user_id(),
        'comment_status' => 'closed',
        'ping_status'   => 'closed'
    ];
    
    $page_id = wp_insert_post($page_data);
    
    if (!is_wp_error($page_id)) {
        update_option('btr_payment_selection_page_id', $page_id);
        wp_send_json_success(['page_id' => $page_id]);
    } else {
        wp_send_json_error(['message' => $page_id->get_error_message()]);
    }
});