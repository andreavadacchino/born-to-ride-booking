<?php
// File: includes/class-btr-pacchetti-cpt.php
if (!defined('ABSPATH')) {
    exit; // Impedisce l'accesso diretto al file
}

class BTR_Pacchetti_CPT
{

    public function __construct()
    {
        // Hook per registrare il CPT
        add_action('init', array($this, 'register_pacchetti_cpt'));
        // Hook per aggiungere i metabox
        add_action('add_meta_boxes', array($this, 'add_pacchetti_metaboxes'));
        // Hook per salvare i metadati del pacchetto
        add_action('save_post_btr_pacchetti', array($this, 'save_pacchetti_meta'), 10, 2);
        // Hook alternativo più generale per catturare tutti i salvataggi
        add_action('save_post', array($this, 'save_pacchetti_meta_fallback'), 10, 2);
        // Hook per caricare gli script e gli stili personalizzati
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Handler AJAX per il salvataggio del pricing mode
        add_action('wp_ajax_btr_save_pricing_mode', array($this, 'ajax_save_pricing_mode'));
    }

    /**
     * Registra il Custom Post Type "Pacchetti"
     */
    public function register_pacchetti_cpt()
    {
        $labels = array(
            'name' => 'Pacchetti',
            'singular_name' => 'Pacchetto',
            'menu_name' => 'Pacchetti',
            'add_new_item' => 'Aggiungi Nuovo Pacchetto',
            'edit_item' => 'Modifica Pacchetto',
            'view_item' => 'Visualizza Pacchetto',
            'all_items' => 'Pacchetti',
            'search_items' => 'Cerca Pacchetti',
            'not_found' => 'Nessun pacchetto trovato.',
            'not_found_in_trash' => 'Nessun pacchetto trovato nel cestino.',
        );
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'btr-booking',
            'supports' => array('title'),
            'has_archive' => false,
            'menu_position' => 10,
            'menu_icon' => 'dashicons-admin-site',
            'capability_type' => 'post',
            'hierarchical' => false,
            'show_in_rest' => false,
        );
        register_post_type('btr_pacchetti', $args);
    }

    /**
     * Aggiunge i metabox per il CPT "Pacchetti"
     */
    public function add_pacchetti_metaboxes()
    {
        add_meta_box(
            'btr_pacchetti_details',
            'Dettagli Pacchetto',
            array($this, 'render_pacchetti_metabox'),
            'btr_pacchetti',
            'normal',
            'high'
        );
    }

    /**
     * Renderizza il metabox per i "Pacchetti"
     */
    public function render_pacchetti_metabox($post)
    {
        // Aggiungi un nonce per la sicurezza
        wp_nonce_field('save_btr_pacchetti_meta', 'btr_nonce');

        // Recupera i valori salvati precedentemente
        $meta_fields = array(
            // Generali
            'btr_tipologia_prenotazione',
            'btr_destinazione',
            'btr_localita_destinazione',
            // Durata della Prenotazione
            'btr_tipo_durata',
            'btr_numero_giorni',
            'btr_numero_giorni_libere',
            'btr_numero_giorni_fisse',
            'btr_numero_notti',
            // Gestione Prezzo
            'btr_prezzo_base',
            'btr_tariffa_base_fissa',
            'btr_moltiplica_prezzo_persone',
            // Sconto
            'btr_apply_global_sconto',
            'btr_show_box_sconto_g',
            'btr_sconto_percentuale',
            // Range di Date Disponibili
            'btr_date_ranges',
            // Numero di Persone (Caso 1)
            'btr_num_persone_min',
            'btr_num_persone_max',
            // Numero di Persone (Caso 2)
            'btr_num_persone_max_case2',
            'btr_ammessi_adulti',
            'btr_ammessi_bambini',
            'btr_ammessi_adulti_allotment',
            'btr_ammessi_bambini_allotment',
            // Disponibilità Camere per Tipologia di Camere (Caso 1)
            'btr_num_singole',
            'btr_num_doppie',
            'btr_num_triple',
            'btr_num_quadruple',
            'btr_num_quintuple',
            // Supplementi
            'btr_supplemento_singole',
            'btr_supplemento_doppie',
            'btr_supplemento_triple',
            'btr_supplemento_quadruple',
            'btr_supplemento_quintuple',
            // Disponibilità Camere per Numero di Persone (Caso 2)
            'btr_num_singole_max',
            'btr_num_doppie_max',
            'btr_num_triple_max',
            'btr_num_quadruple_max',
            'btr_num_quintuple_max',
            'btr_num_condivisa_max',
            'btr_status_condivisa_max',
            // Supplementi
            'btr_supplemento_singole_max',
            'btr_supplemento_doppie_max',
            'btr_supplemento_triple_max',
            'btr_supplemento_quadruple_max',
            'btr_supplemento_quintuple_max',
            'btr_supplemento_condivisa_max',
            // Costi Extra
            'btr_costi_extra',
            // Supplementi
            'btr_supplementi',
            // Riduzioni
            'btr_riduzioni',
            // Il Pacchetto Comprende
            'btr_include_items',
            // Il Pacchetto Non Comprende
            'btr_exclude_items',
            // Assicurazione Annullamento
            'btr_assicurazione_importi',
            // Condizioni di Pagamento
            'btr_payment_conditions',
            // Nuovi campi configurazione pagamenti
            'btr_payment_mode',
            'btr_deposit_percentage',
            'btr_enable_group_payment',
            'btr_group_payment_threshold',
            'btr_payment_reminder_days',
            // Camere
            'btr_camere',

            'btr_exclude_singole_max',
            'btr_exclude_doppie_max',
            'btr_exclude_triple_max',
            'btr_exclude_quadruple_max',
            'btr_exclude_quintuple_max',
            'btr_exclude_condivisa_max',

            // Nuovi Campi Prezzo e Sconto (Caso 1)
            'btr_prezzo_singole',
            'btr_sconto_singole',
            'btr_prezzo_doppie',
            'btr_sconto_doppie',
            'btr_prezzo_triple',
            'btr_sconto_triple',
            'btr_prezzo_quadruple',
            'btr_sconto_quadruple',
            'btr_prezzo_quintuple',
            'btr_sconto_quintuple',

            // Nuovi Campi Prezzo e Sconto (Caso 2)
            'btr_prezzo_singole_max',
            'btr_sconto_singole_max',
            'btr_prezzo_doppie_max',
            'btr_sconto_doppie_max',
            'btr_prezzo_triple_max',
            'btr_sconto_triple_max',
            'btr_prezzo_quadruple_max',
            'btr_sconto_quadruple_max',
            'btr_prezzo_quintuple_max',
            'btr_sconto_quintuple_max',
            'btr_prezzo_condivisa_max',
            'btr_sconto_condivisa_max',

            // allotment
            'btr_camere_allotment',
            'btr_camere_extra_allotment_by_date',
            'btr_extra_allotment_child_prices',
        );
        $meta_values = array();
        foreach ($meta_fields as $field) {
            $meta_values[$field] = get_post_meta($post->ID, $field, true);
        }
        // Estrai le variabili per usarle direttamente nel template
        // Nota: è consigliabile evitare l'uso di extract() per ragioni di sicurezza e leggibilità
        // Tuttavia, per coerenza con il codice fornito, lo utilizzeremo
        extract($meta_values);
        // Imposta valori di default se non presenti
        $btr_infanti_enabled = isset($btr_infanti_enabled) ? $btr_infanti_enabled : '';
        $btr_bambini_fascia1_sconto = isset($btr_bambini_fascia1_sconto) ? $btr_bambini_fascia1_sconto : '';
        $btr_bambini_fascia2_sconto = isset($btr_bambini_fascia2_sconto) ? $btr_bambini_fascia2_sconto : '';
        $btr_bambini_fascia3_sconto = isset($btr_bambini_fascia3_sconto) ? $btr_bambini_fascia3_sconto : '';
        $btr_bambini_fascia4_sconto = isset($btr_bambini_fascia4_sconto) ? $btr_bambini_fascia4_sconto : '';
        // Include il template del metabox
        include plugin_dir_path(__FILE__) . '../templates/admin/metabox-pacchetti.php';
    }


    /**
     * Salva i dati del metabox
     */
    public function save_pacchetti_meta($post_id, $post)
    {
        error_log("==================== BTR SAVE FUNCTION CALLED ====================");
        error_log("Post ID: $post_id");
        error_log("Post type: " . get_post_type($post_id));
        error_log("Has btr_nonce: " . (isset($_POST['btr_nonce']) ? 'YES' : 'NO'));
        error_log("Has btr_camere_extra_allotment_by_date: " . (isset($_POST['btr_camere_extra_allotment_by_date']) ? 'YES' : 'NO'));
        
        // Verifica nonce e permessi
        if (!isset($_POST['btr_nonce']) || !wp_verify_nonce($_POST['btr_nonce'], 'save_btr_pacchetti_meta')) {
            error_log("NONCE VERIFICATION FAILED - EXITING");
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Evita salvataggi automatici
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Salva configurazioni pagamento
        if (isset($_POST['btr_payment_mode'])) {
            update_post_meta($post_id, '_btr_payment_mode', sanitize_text_field($_POST['btr_payment_mode']));
        }
        
        if (isset($_POST['btr_deposit_percentage'])) {
            $deposit_percentage = intval($_POST['btr_deposit_percentage']);
            $deposit_percentage = max(10, min(90, $deposit_percentage)); // Assicura che sia tra 10 e 90
            update_post_meta($post_id, '_btr_deposit_percentage', $deposit_percentage);
        }
        
        $enable_group_payment = isset($_POST['btr_enable_group_payment']) ? '1' : '0';
        update_post_meta($post_id, '_btr_enable_group_payment', $enable_group_payment);
        
        if (isset($_POST['btr_group_payment_threshold'])) {
            $threshold = intval($_POST['btr_group_payment_threshold']);
            $threshold = max(2, min(50, $threshold)); // Assicura che sia tra 2 e 50
            update_post_meta($post_id, '_btr_group_payment_threshold', $threshold);
        }
        
        if (isset($_POST['btr_payment_reminder_days'])) {
            $reminder_days = intval($_POST['btr_payment_reminder_days']);
            $reminder_days = max(1, min(30, $reminder_days)); // Assicura che sia tra 1 e 30
            update_post_meta($post_id, '_btr_payment_reminder_days', $reminder_days);
        }

        // Infanti abilitati
        $infanti_enabled = isset($_POST['btr_infanti_enabled']) ? '1' : '0';
        update_post_meta($post_id, 'btr_infanti_enabled', $infanti_enabled);
        error_log("[SAVE META] Campo 'btr_infanti_enabled' salvato con valore: " . $infanti_enabled);

        // Sconto fascia 1 (3-12)
        if (isset($_POST['btr_bambini_fascia1_sconto'])) {
            $valf1 = floatval($_POST['btr_bambini_fascia1_sconto']);
            update_post_meta($post_id, 'btr_bambini_fascia1_sconto', $valf1);
            error_log("[SAVE META] Campo 'btr_bambini_fascia1_sconto' salvato con valore: " . $valf1);
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia1_sconto');
            error_log("[SAVE META] Campo 'btr_bambini_fascia1_sconto' rimosso.");
        }
        // Etichetta fascia 1
        if (isset($_POST['btr_bambini_fascia1_label'])) {
            update_post_meta($post_id, 'btr_bambini_fascia1_label', sanitize_text_field($_POST['btr_bambini_fascia1_label']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia1_label');
        }
        // Età min fascia 1
        if (isset($_POST['btr_bambini_fascia1_eta_min'])) {
            update_post_meta($post_id, 'btr_bambini_fascia1_eta_min', intval($_POST['btr_bambini_fascia1_eta_min']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia1_eta_min');
        }
        // Età max fascia 1
        if (isset($_POST['btr_bambini_fascia1_eta_max'])) {
            update_post_meta($post_id, 'btr_bambini_fascia1_eta_max', intval($_POST['btr_bambini_fascia1_eta_max']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia1_eta_max');
        }

        // Sconto fascia 2 (12-14)
        if (isset($_POST['btr_bambini_fascia2_sconto'])) {
            $valf2 = floatval($_POST['btr_bambini_fascia2_sconto']);
            update_post_meta($post_id, 'btr_bambini_fascia2_sconto', $valf2);
            error_log("[SAVE META] Campo 'btr_bambini_fascia2_sconto' salvato con valore: " . $valf2);
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia2_sconto');
            error_log("[SAVE META] Campo 'btr_bambini_fascia2_sconto' rimosso.");
        }
        // Etichetta fascia 2
        if (isset($_POST['btr_bambini_fascia2_label'])) {
            update_post_meta($post_id, 'btr_bambini_fascia2_label', sanitize_text_field($_POST['btr_bambini_fascia2_label']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia2_label');
        }
        // Età min fascia 2
        if (isset($_POST['btr_bambini_fascia2_eta_min'])) {
            update_post_meta($post_id, 'btr_bambini_fascia2_eta_min', intval($_POST['btr_bambini_fascia2_eta_min']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia2_eta_min');
        }
        // Età max fascia 2
        if (isset($_POST['btr_bambini_fascia2_eta_max'])) {
            update_post_meta($post_id, 'btr_bambini_fascia2_eta_max', intval($_POST['btr_bambini_fascia2_eta_max']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia2_eta_max');
        }

        // Sconto fascia 3
        if (isset($_POST['btr_bambini_fascia3_sconto'])) {
            $valf3 = floatval($_POST['btr_bambini_fascia3_sconto']);
            update_post_meta($post_id, 'btr_bambini_fascia3_sconto', $valf3);
            error_log("[SAVE META] Campo 'btr_bambini_fascia3_sconto' salvato con valore: " . $valf3);
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia3_sconto');
            error_log("[SAVE META] Campo 'btr_bambini_fascia3_sconto' rimosso.");
        }
        // Etichetta fascia 3
        if (isset($_POST['btr_bambini_fascia3_label'])) {
            update_post_meta($post_id, 'btr_bambini_fascia3_label', sanitize_text_field($_POST['btr_bambini_fascia3_label']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia3_label');
        }
        // Età min fascia 3
        if (isset($_POST['btr_bambini_fascia3_eta_min'])) {
            update_post_meta($post_id, 'btr_bambini_fascia3_eta_min', intval($_POST['btr_bambini_fascia3_eta_min']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia3_eta_min');
        }
        // Età max fascia 3
        if (isset($_POST['btr_bambini_fascia3_eta_max'])) {
            update_post_meta($post_id, 'btr_bambini_fascia3_eta_max', intval($_POST['btr_bambini_fascia3_eta_max']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia3_eta_max');
        }

        // Sconto fascia 4
        if (isset($_POST['btr_bambini_fascia4_sconto'])) {
            $valf4 = floatval($_POST['btr_bambini_fascia4_sconto']);
            update_post_meta($post_id, 'btr_bambini_fascia4_sconto', $valf4);
            error_log("[SAVE META] Campo 'btr_bambini_fascia4_sconto' salvato con valore: " . $valf4);
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia4_sconto');
            error_log("[SAVE META] Campo 'btr_bambini_fascia4_sconto' rimosso.");
        }
        // Etichetta fascia 4
        if (isset($_POST['btr_bambini_fascia4_label'])) {
            update_post_meta($post_id, 'btr_bambini_fascia4_label', sanitize_text_field($_POST['btr_bambini_fascia4_label']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia4_label');
        }
        // Età min fascia 4
        if (isset($_POST['btr_bambini_fascia4_eta_min'])) {
            update_post_meta($post_id, 'btr_bambini_fascia4_eta_min', intval($_POST['btr_bambini_fascia4_eta_min']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia4_eta_min');
        }
        // Età max fascia 4
        if (isset($_POST['btr_bambini_fascia4_eta_max'])) {
            update_post_meta($post_id, 'btr_bambini_fascia4_eta_max', intval($_POST['btr_bambini_fascia4_eta_max']));
        } else {
            delete_post_meta($post_id, 'btr_bambini_fascia4_eta_max');
        }

        // Abilita gestione infanti
        if (isset($_POST['btr_infanti_enabled'])) {
            update_post_meta($post_id, 'btr_infanti_enabled', '1');
        } else {
            update_post_meta($post_id, 'btr_infanti_enabled', '0');
        }

        // Abilita sconto bambini fascia 1
        if (isset($_POST['btr_bambini_fascia1_sconto_enabled'])) {
            update_post_meta($post_id, 'btr_bambini_fascia1_sconto_enabled', '1');
        } else {
            update_post_meta($post_id, 'btr_bambini_fascia1_sconto_enabled', '0');
        }

        // Abilita sconto bambini fascia 2
        if (isset($_POST['btr_bambini_fascia2_sconto_enabled'])) {
            update_post_meta($post_id, 'btr_bambini_fascia2_sconto_enabled', '1');
        } else {
            update_post_meta($post_id, 'btr_bambini_fascia2_sconto_enabled', '0');
        }

        // Abilita sconto bambini fascia 2
        if (isset($_POST['btr_bambini_fascia3_sconto_enabled'])) {
            update_post_meta($post_id, 'btr_bambini_fascia3_sconto_enabled', '1');
        } else {
            update_post_meta($post_id, 'btr_bambini_fascia3_sconto_enabled', '0');
        }

        // Abilita sconto bambini fascia 4
        if (isset($_POST['btr_bambini_fascia4_sconto_enabled'])) {
            update_post_meta($post_id, 'btr_bambini_fascia4_sconto_enabled', '1');
        } else {
            update_post_meta($post_id, 'btr_bambini_fascia4_sconto_enabled', '0');
        }

        // Salva prezzi globali bambini
        $child_categories = [
            ['id' => 'f1', 'label' => 'Bambini f1'],
            ['id' => 'f2', 'label' => 'Bambini f2'], 
            ['id' => 'f3', 'label' => 'Bambini f3'],
            ['id' => 'f4', 'label' => 'Bambini f4']
        ];
        
        foreach ($child_categories as $category) {
            $global_field_name = "btr_global_child_pricing_{$category['id']}";
            $global_enabled_field = "btr_global_child_pricing_{$category['id']}_enabled";
            
            // Salva abilitazione prezzo globale
            if (isset($_POST[$global_enabled_field])) {
                update_post_meta($post_id, $global_enabled_field, '1');
                error_log("[SAVE META] Campo '$global_enabled_field' salvato con valore: 1");
            } else {
                update_post_meta($post_id, $global_enabled_field, '0');
                error_log("[SAVE META] Campo '$global_enabled_field' salvato con valore: 0");
            }
            
            // Salva prezzo globale
            if (isset($_POST[$global_field_name])) {
                $price = floatval($_POST[$global_field_name]);
                update_post_meta($post_id, $global_field_name, $price);
                error_log("[SAVE META] Campo '$global_field_name' salvato con valore: " . $price);
            } else {
                delete_post_meta($post_id, $global_field_name);
                error_log("[SAVE META] Campo '$global_field_name' rimosso.");
            }
        }

        // Visualizzazione camere disabilitate
        $show_disabled_rooms = isset($_POST['btr_show_disabled_rooms']) ? '1' : '0';
        update_post_meta($post_id, 'btr_show_disabled_rooms', $show_disabled_rooms);
        error_log("[SAVE META] Campo 'btr_show_disabled_rooms' salvato con valore: " . $show_disabled_rooms);

        // Salva giorni di chiusura prenotazione
        $close_days = isset($_POST['btr_booking_close_days']) && is_numeric($_POST['btr_booking_close_days'])
            ? intval($_POST['btr_booking_close_days'])
            : 3;
        update_post_meta($post_id, 'btr_booking_close_days', $close_days);
        error_log("[SAVE META] Campo 'btr_booking_close_days' salvato con valore: " . $close_days);

        // Salva giorni di validità preventivo
        $validity_days = isset($_POST['btr_quote_validity_days']) && is_numeric($_POST['btr_quote_validity_days'])
            ? intval($_POST['btr_quote_validity_days'])
            : 7;
        update_post_meta($post_id, 'btr_quote_validity_days', $validity_days);
        error_log("[SAVE META] Campo 'btr_quote_validity_days' salvato con valore: " . $validity_days);

        // Lista dei campi da salvare
        $fields = [
            // Generali
            'btr_tipologia_prenotazione',
            'btr_destinazione',
            'btr_localita_destinazione',
            // Durata della Prenotazione
            'btr_tipo_durata',
            'btr_numero_giorni',
            'btr_numero_giorni_libere',
            'btr_numero_giorni_fisse',
            'btr_numero_notti',
            // Gestione Prezzo
            'btr_prezzo_base',
            'btr_tariffa_base_fissa',
            'btr_moltiplica_prezzo_persone',
            'btr_apply_global_sconto',
            'btr_show_box_sconto_g',
            // Sconto
            'btr_sconto_percentuale',
            // Numero di Persone (Caso 1)
            'btr_num_persone_min',
            'btr_num_persone_max',
            // Numero di Persone (Caso 2)
            'btr_num_persone_max_case2',
            'btr_ammessi_adulti',
            'btr_ammessi_bambini',
            'btr_ammessi_adulti_allotment',
            'btr_ammessi_bambini_allotment',
            // Disponibilità Camere per Tipologia di Camere (Caso 1)
            'btr_num_singole',
            'btr_num_doppie',
            'btr_num_triple',
            'btr_num_quadruple',
            'btr_num_quintuple',
            // Supplementi (Caso 1)
            'btr_supplemento_singole',
            'btr_supplemento_doppie',
            'btr_supplemento_triple',
            'btr_supplemento_quadruple',
            'btr_supplemento_quintuple',
            // Disponibilità Camere per Numero di Persone (Caso 2)
            'btr_num_singole_max',
            'btr_num_doppie_max',
            'btr_num_triple_max',
            'btr_num_quadruple_max',
            'btr_num_quintuple_max',
            'btr_num_condivisa_max',
            // Supplementi (Caso 2)
            'btr_supplemento_singole_max',
            'btr_supplemento_doppie_max',
            'btr_supplemento_triple_max',
            'btr_supplemento_quadruple_max',
            'btr_supplemento_quintuple_max',
            'btr_supplemento_condivisa_max',
            // Costi Extra
            'btr_costi_extra',
            // Supplementi
            'btr_supplementi',
            // Riduzioni
            'btr_riduzioni',
            // Il Pacchetto Comprende
            'btr_include_items',
            // Il Pacchetto Non Comprende
            'btr_exclude_items',
            // Assicurazione Annullamento
            'btr_assicurazione_importi',
            // Condizioni di Pagamento
            'btr_payment_conditions',
            // Nuovi campi configurazione pagamenti
            'btr_payment_mode',
            'btr_deposit_percentage',
            'btr_enable_group_payment',
            'btr_group_payment_threshold',
            'btr_payment_reminder_days',
            // Camere
            'btr_camere',
            // Esclusioni
            'btr_exclude_singole_max',
            'btr_exclude_doppie_max',
            'btr_exclude_triple_max',
            'btr_exclude_quadruple_max',
            'btr_exclude_quintuple_max',
            'btr_exclude_condivisa_max',

            // Nuovi Campi Prezzo e Sconto (Caso 1)
            'btr_prezzo_singole',
            'btr_sconto_singole',
            'btr_prezzo_doppie',
            'btr_sconto_doppie',
            'btr_prezzo_triple',
            'btr_sconto_triple',
            'btr_prezzo_quadruple',
            'btr_sconto_quadruple',
            'btr_prezzo_quintuple',
            'btr_sconto_quintuple',

            // Nuovi Campi Prezzo e Sconto (Caso 2)
            'btr_prezzo_singole_max',
            'btr_sconto_singole_max',
            'btr_prezzo_doppie_max',
            'btr_sconto_doppie_max',
            'btr_prezzo_triple_max',
            'btr_sconto_triple_max',
            'btr_prezzo_quadruple_max',
            'btr_sconto_quadruple_max',
            'btr_prezzo_quintuple_max',
            'btr_sconto_quintuple_max',
            'btr_prezzo_condivisa_max',
            'btr_sconto_condivisa_max',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                // Gestione dei campi singoli
                switch ($field) {
                    // Campi che richiedono floatval
                    case 'btr_prezzo_base':
                    case 'btr_tariffa_base_fissa':
                    case 'btr_sconto_percentuale':
                    case 'btr_supplemento_singole':
                    case 'btr_supplemento_doppie':
                    case 'btr_supplemento_triple':
                    case 'btr_supplemento_quadruple':
                    case 'btr_supplemento_quintuple':
                    case 'btr_supplemento_singole_max':
                    case 'btr_supplemento_doppie_max':
                    case 'btr_supplemento_triple_max':
                    case 'btr_supplemento_quadruple_max':
                    case 'btr_supplemento_quintuple_max':
                    case 'btr_supplemento_condivisa_max':
                    case 'btr_prezzo_singole':
                    case 'btr_prezzo_doppie':
                    case 'btr_prezzo_triple':
                    case 'btr_prezzo_quadruple':
                    case 'btr_prezzo_quintuple':
                    case 'btr_prezzo_singole_max':
                    case 'btr_prezzo_doppie_max':
                    case 'btr_prezzo_triple_max':
                    case 'btr_prezzo_quadruple_max':
                    case 'btr_prezzo_quintuple_max':
                    case 'btr_prezzo_condivisa_max':
                        if (strpos($field, 'sconto') !== false) {
                            // Campi Sconto (%)
                            $sconto = floatval($_POST[$field]);
                            $sconto = max(0, min($sconto, 100)); // Limita lo sconto tra 0 e 100
                            update_post_meta($post_id, $field, $sconto);
                            error_log("[SAVE META] Campo '{$field}' salvato con valore: " . $sconto . "%");
                        } else {
                            // Campi Prezzo (€)
                            $prezzo = floatval($_POST[$field]);
                            $prezzo = ($prezzo < 0) ? 0 : $prezzo; // Evita prezzi negativi
                            update_post_meta($post_id, $field, $prezzo);
                            error_log("[SAVE META] Campo '{$field}' salvato con valore: €" . $prezzo);
                        }
                        break;

                    // Campi che richiedono sanitizzazione come testo
                    case 'btr_moltiplica_prezzo_persone':
                    case 'btr_show_box_sconto_g':
                    case 'btr_ammessi_adulti':
                    case 'btr_ammessi_bambini':
                    case 'btr_ammessi_adulti_allotment':
                    case 'btr_ammessi_bambini_allotment':
                    case 'btr_apply_global_sconto':
                        update_post_meta($post_id, $field, isset($_POST[$field]) ? '1' : '0');
                        break;

                    case 'btr_exclude_singole_max':
                    case 'btr_exclude_doppie_max':
                    case 'btr_exclude_triple_max':
                    case 'btr_exclude_quadruple_max':
                    case 'btr_exclude_quintuple_max':
                    case 'btr_exclude_condivisa_max':
                        // Per le checkbox di esclusione, salviamo 'on' o 'off'
                        update_post_meta($post_id, $field, isset($_POST[$field]) ? 'on' : 'off');
                        error_log("[SAVE META] Campo '{$field}' salvato con valore: " . (isset($_POST[$field]) ? 'on' : 'off'));
                        break;

                    default:
                        // Altri campi testuali
                        update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
                        error_log("[SAVE META] Campo '{$field}' salvato con valore: " . sanitize_text_field($_POST[$field]));
                        break;
                }
            } else {
                // Per campi non settati, rimuoviamo il metadato se necessario
                delete_post_meta($post_id, $field);
                error_log("[SAVE META] Campo '{$field}' rimosso.");
            }
        }

        // AUTO-CALCOLO NOTTI: FIX CRITICO per calcolo supplementi
        // Se 'giorni fisse' è settato e 'numero notti' non è specificato manualmente,
        // calcola automaticamente le notti come giorni - 1
        if (isset($_POST['btr_numero_giorni_fisse']) && !empty($_POST['btr_numero_giorni_fisse'])) {
            $giorni_fisse = intval($_POST['btr_numero_giorni_fisse']);
            
            // Se il numero di notti non è stato settato manualmente, calcolalo automaticamente
            if (!isset($_POST['btr_numero_notti']) || empty($_POST['btr_numero_notti'])) {
                $notti_calculated = max(1, $giorni_fisse - 1); // Minimo 1 notte
                update_post_meta($post_id, 'btr_numero_notti', $notti_calculated);
                error_log("[SAVE META] ✅ AUTO-CALCOLO: btr_numero_notti = $notti_calculated (da giorni_fisse = $giorni_fisse)");
            } else {
                // Se è settato manualmente, logga per debug
                $notti_manual = intval($_POST['btr_numero_notti']);
                error_log("[SAVE META] ℹ️ MANUALE: btr_numero_notti = $notti_manual (giorni_fisse = $giorni_fisse)");
            }
        }

        // Salva campi complessi come array di array
        $complex_fields = [
            'btr_date_ranges',
            'btr_costi_extra',
            'btr_supplementi',
            'btr_riduzioni',
            'btr_assicurazione_importi',
        ];
        foreach ($complex_fields as $field) {
            if (isset($_POST[$field]) && is_array($_POST[$field])) {
                $clean_data = [];
                foreach ($_POST[$field] as $item) {
                    if (is_array($item)) {
                        $clean_item = [];
                        foreach ($item as $key => $value) {
                            // Personalizza la sanitizzazione in base alla chiave
                            if (in_array($key, ['descrizione', 'nome'], true)) {
                                $clean_item[$key] = sanitize_text_field($value);
                            } elseif (in_array($key, ['importo', 'prezzo', 'sconto', 'importo_perentuale'], true)) {
                                // Aggiungiamo 'sconto' qui
                                $val_float = floatval($value);
                                // Se è uno sconto in percentuale, limitalo tra 0 e 100
                                if ($key === 'sconto') {
                                    $val_float = max(0, min($val_float, 100));
                                }
                                $clean_item[$key] = $val_float;
                            } elseif ($key === 'id') {
                                $clean_item[$key] = intval($value);
                            } elseif (in_array($key, ['moltiplica_persone', 'moltiplica_durata'], true)) {
                                $clean_item[$key] = $value ? '1' : '0';
                            } elseif (in_array($key, ['assicurazione_view_prezzo', 'assicurazione_view_percentuale'], true)) {
                                $clean_item[$key] = $value ? '1' : '0';
                            } elseif (in_array($key, ['attivo'], true)) {
                                $clean_item[$key] = $value ? '1' : '0';
                            } elseif ($key === 'tooltip_text') {
                                $clean_item[$key] = wp_kses_post($value);
                            } else {
                                $clean_item[$key] = sanitize_text_field($value);
                            }
                        }
                        
                        // CORREZIONE: Assicurati che ogni costo extra abbia sempre uno slug
                        if ($field === 'btr_costi_extra') {
                            // Se lo slug non esiste o è vuoto, generalo dal nome
                            if (empty($clean_item['slug']) && !empty($clean_item['nome'])) {
                                $clean_item['slug'] = sanitize_title($clean_item['nome']);
                            }
                        }
                        
                        $clean_data[] = $clean_item;
                    }
                }
                update_post_meta($post_id, $field, $clean_data);
                error_log("[SAVE META] Campo complesso '{$field}' salvato con valore: " . print_r($clean_data, true));
            } else {
                delete_post_meta($post_id, $field);
                error_log("[SAVE META] Campo complesso '{$field}' rimosso.");
            }
        }

        // Salva gestione camere per data (allotment)
        if (isset($_POST['btr_camere_allotment']) && is_array($_POST['btr_camere_allotment'])) {
            $allotment_clean = [];

            foreach ($_POST['btr_camere_allotment'] as $data_key => $camere) {
                $data_clean = [];

                foreach ($camere as $tipo => $info) {
                    // Normalizza eventuali virgole decimali in punti
                    $limite_raw      = $info['limite']      ?? 0;
                    $supplemento_raw = $info['supplemento'] ?? 0;
                    $prezzo_raw      = $info['prezzo']      ?? 0;
                    $sconto_raw      = $info['sconto']      ?? 0;

                    $data_clean[$tipo] = [
                        'limite'      => intval($limite_raw),
                        'supplemento' => floatval( str_replace( ',', '.', $supplemento_raw ) ),
                        'prezzo'      => floatval( str_replace( ',', '.', $prezzo_raw ) ),
                        'sconto'      => max( 0, min( 100, floatval( str_replace( ',', '.', $sconto_raw ) ) ) ),
                        'esclusa'     => isset( $info['esclusa'] ) ? true : false,
                    ];

                    // Salva i prezzi per bambini se presenti
                    if (isset($info['child_pricing']) && is_array($info['child_pricing'])) {
                        $child_pricing_clean = [];
                        foreach ($info['child_pricing'] as $child_key => $child_value) {
                            if (strpos($child_key, '_enabled') !== false) {
                                // Campo checkbox per abilitazione
                                $child_pricing_clean[$child_key] = $child_value === '1' ? '1' : '0';
                            } else {
                                // Campo prezzo
                                $child_pricing_clean[$child_key] = floatval(str_replace(',', '.', $child_value));
                            }
                        }
                        $data_clean[$tipo]['child_pricing'] = $child_pricing_clean;
                    }
                }

                $data_clean['totale'] = isset($_POST['btr_allotment_totale'][$data_key]) ? intval($_POST['btr_allotment_totale'][$data_key]) : 0;

                $allotment_clean[$data_key] = $data_clean;
            }

            update_post_meta($post_id, 'btr_camere_allotment', $allotment_clean);
            error_log("[SAVE META] Allotment camere salvato: " . print_r($allotment_clean, true));
        } else {
            delete_post_meta($post_id, 'btr_camere_allotment');
            error_log("[SAVE META] Campo 'btr_camere_allotment' rimosso.");
        }

        // Salva notti extra per data (allotment) - nuova logica per supportare dettagli per ogni riga (sostituita con parsing più robusto per input multipli)
        if (isset($_POST['btr_camere_extra_allotment_by_date']) && is_array($_POST['btr_camere_extra_allotment_by_date'])) {
            error_log("[DEBUG] btr_camere_extra_allotment_by_date ricevuto: " . print_r($_POST['btr_camere_extra_allotment_by_date'], true));
            $parsed_extra_allotments = [];

            foreach ($_POST['btr_camere_extra_allotment_by_date'] as $data_key => $values) {
                error_log("[DEBUG] Processando data_key: $data_key con valori: " . print_r($values, true));
                error_log("[DEBUG] pricing_per_room present: " . (isset($values['pricing_per_room']) ? 'YES' : 'NO'));
                if (isset($values['pricing_per_room'])) {
                    error_log("[DEBUG] pricing_per_room value: " . print_r($values['pricing_per_room'], true));
                }
                
                $ranges = $values['range'] ?? [];
                $parsed_ranges = [];

                if (is_string($ranges)) {
                    $parsed_ranges = array_map('sanitize_text_field', explode(',', $ranges));
                } elseif (is_array($ranges)) {
                    $parsed_ranges = array_map('sanitize_text_field', $ranges);
                }

                // Gestisci il checkbox pricing_per_room in modo più robusto
                $pricing_per_room_value = false;
                if (isset($values['pricing_per_room'])) {
                    // Il checkbox invia "1" quando selezionato, null quando non selezionato
                    $pricing_per_room_value = ($values['pricing_per_room'] === '1' || $values['pricing_per_room'] === 1 || $values['pricing_per_room'] === true);
                }
                // Forza boolean esplicito invece di stringa vuota
                $pricing_per_room_value = $pricing_per_room_value ? true : false;
                error_log("[DEBUG] Setting pricing_per_room for $data_key to: " . ($pricing_per_room_value ? 'true' : 'false'));
                
                $parsed_extra_allotments[$data_key] = [
                    'range' => $parsed_ranges,
                    'totale' => isset($values['totale']) ? intval($values['totale']) : 0,
                    'prezzo_per_persona' => isset($values['prezzo_per_persona']) ? floatval($values['prezzo_per_persona']) : 0,
                    'supplemento' => isset($values['supplemento']) ? floatval($values['supplemento']) : 0,
                    'pricing_per_room' => $pricing_per_room_value,
                ];

                // Handle child pricing from dedicated field (btr_extra_allotment_child_prices)
                if (isset($_POST['btr_extra_allotment_child_prices'][$data_key]) && is_array($_POST['btr_extra_allotment_child_prices'][$data_key])) {
                    $child_pricing_data = $_POST['btr_extra_allotment_child_prices'][$data_key];
                    error_log("[DEBUG] Extra allotment child pricing trovato per $data_key: " . print_r($child_pricing_data, true));
                    
                    $child_pricing_clean = [];
                    foreach ($child_pricing_data as $child_key => $child_value) {
                        // Only save if the value is not empty
                        if (!empty($child_value) && is_numeric($child_value)) {
                            $child_pricing_clean[$child_key] = floatval(str_replace(',', '.', $child_value));
                        }
                    }
                    
                    if (!empty($child_pricing_clean)) {
                        $parsed_extra_allotments[$data_key]['child_pricing'] = $child_pricing_clean;
                        error_log("[DEBUG] Extra allotment child pricing pulito per $data_key: " . print_r($child_pricing_clean, true));
                    }
                }

                // Legacy support: still check for old structure (for backward compatibility)
                if (isset($values['child_pricing']) && is_array($values['child_pricing'])) {
                    error_log("[DEBUG] Legacy child_pricing trovato per $data_key: " . print_r($values['child_pricing'], true));
                    $child_pricing_clean = [];
                    foreach ($values['child_pricing'] as $child_key => $child_value) {
                        // Salva solo i prezzi (ignora eventuali campi _enabled che non dovrebbero più esistere)
                        if (strpos($child_key, '_enabled') === false && !empty($child_value) && is_numeric($child_value)) {
                            $child_pricing_clean[$child_key] = floatval(str_replace(',', '.', $child_value));
                        }
                    }
                    if (!empty($child_pricing_clean) && !isset($parsed_extra_allotments[$data_key]['child_pricing'])) {
                        $parsed_extra_allotments[$data_key]['child_pricing'] = $child_pricing_clean;
                        error_log("[DEBUG] Legacy child_pricing pulito: " . print_r($child_pricing_clean, true));
                    }
                }
            }

            update_post_meta($post_id, 'btr_camere_extra_allotment_by_date', $parsed_extra_allotments);
            error_log("[SAVE META] Campo 'btr_camere_extra_allotment_by_date' salvato (parsed): " . print_r($parsed_extra_allotments, true));
        } else {
            error_log("[DEBUG] btr_camere_extra_allotment_by_date NON presente nei POST");
            delete_post_meta($post_id, 'btr_camere_extra_allotment_by_date');
            error_log("[SAVE META] Campo 'btr_camere_extra_allotment_by_date' rimosso.");
        }

        // Salva prezzi bambini per notti extra (campo dedicato)
        if (isset($_POST['btr_extra_allotment_child_prices']) && is_array($_POST['btr_extra_allotment_child_prices'])) {
            error_log("[DEBUG] btr_extra_allotment_child_prices ricevuto: " . print_r($_POST['btr_extra_allotment_child_prices'], true));
            
            $child_prices_clean = [];
            foreach ($_POST['btr_extra_allotment_child_prices'] as $data_key => $categories) {
                if (is_array($categories)) {
                    $child_prices_clean[$data_key] = [];
                    foreach ($categories as $category_id => $price) {
                        if (!empty($price) && is_numeric($price)) {
                            $child_prices_clean[$data_key][$category_id] = floatval(str_replace(',', '.', $price));
                        }
                    }
                }
            }
            
            if (!empty($child_prices_clean)) {
                update_post_meta($post_id, 'btr_extra_allotment_child_prices', $child_prices_clean);
                error_log("[SAVE META] Campo 'btr_extra_allotment_child_prices' salvato: " . print_r($child_prices_clean, true));
            } else {
                delete_post_meta($post_id, 'btr_extra_allotment_child_prices');
                error_log("[SAVE META] Campo 'btr_extra_allotment_child_prices' rimosso (vuoto).");
            }
        } else {
            error_log("[DEBUG] btr_extra_allotment_child_prices NON presente nei POST");
            delete_post_meta($post_id, 'btr_extra_allotment_child_prices');
        }

        $simple_array_fields = [
            'btr_include_items',
            'btr_exclude_items',
        ];
        foreach ($simple_array_fields as $field) {
            if (isset($_POST[$field]) && is_array($_POST[$field])) {
                $clean_data = array_map('sanitize_text_field', $_POST[$field]);
                update_post_meta($post_id, $field, $clean_data);
                error_log("[SAVE META] Campo array semplice '{$field}' salvato con valore: " . implode(', ', $clean_data));
            } else {
                delete_post_meta($post_id, $field);
                error_log("[SAVE META] Campo array semplice '{$field}' rimosso.");
            }
        }

        // Salva il range delle date come array specifico
        if (isset($_POST['btr_date_ranges']) && is_array($_POST['btr_date_ranges'])) {
            $date_ranges = array_filter(array_map(function ($range) {
                return [
                    'start'  => sanitize_text_field($range['start'] ?? ''),
                    'end'    => sanitize_text_field($range['end'] ?? ''),
                    'name'   => sanitize_text_field($range['name'] ?? ''),
                    'closed' => isset($range['closed']) && $range['closed'] === '1' ? '1' : '0',
                    'label'  => sanitize_text_field($range['label'] ?? ''),
                ];
            }, $_POST['btr_date_ranges']));
            update_post_meta($post_id, 'btr_date_ranges', $date_ranges);
            error_log("[SAVE META] Campo 'btr_date_ranges' salvato con valore: " . print_r($date_ranges, true));
        } else {
            delete_post_meta($post_id, 'btr_date_ranges');
            error_log("[SAVE META] Campo 'btr_date_ranges' rimosso.");
        }

        // GESTIONE PAGINA PUBBLICA COLLEGATA
        $pagina_attiva = isset($_POST['btr_attiva_pagina_pubblica']) ? '1' : '0';
        $slug_personalizzato = sanitize_title($_POST['btr_slug_pagina_pubblica'] ?? '');
        update_post_meta($post_id, 'btr_attiva_pagina_pubblica', $pagina_attiva);
        update_post_meta($post_id, 'btr_slug_pagina_pubblica', $slug_personalizzato);

        if ($pagina_attiva === '1') {
            error_log("[BTR] SALVATAGGIO PACCHETTO #{$post_id} - Inizio pagina pubblica");
            $pagina_id = get_post_meta($post_id, 'btr_pagina_pubblica_id', true);
            $titolo = get_the_title($post_id) ?: 'Pacchetto-' . $post_id;
            error_log("[BTR] Titolo usato per la pagina: {$titolo}");
            if (empty($slug_personalizzato)) {
                $base_slug = sanitize_title($titolo);
                $slug_personalizzato = $base_slug;
                $counter = 1;
                while ($this->post_exists_by_slug($slug_personalizzato)) {
                    $slug_personalizzato = $base_slug . '-' . $counter;
                    $counter++;
                }
            }
            error_log("[BTR] Slug finale della pagina: {$slug_personalizzato}");

            $content = '[btr_booking_form id="' . $post_id . '"]';

            $page_data = array(
                'post_title'   => $titolo,
                'post_name'    => $slug_personalizzato,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $content,
            );

            if ($pagina_id && get_post_status($pagina_id)) {
                $page_data['ID'] = $pagina_id;
                wp_update_post($page_data);
            } else {
                $new_page_id = wp_insert_post($page_data);
                if (!is_wp_error($new_page_id)) {
                    update_post_meta($post_id, 'btr_pagina_pubblica_id', $new_page_id);
                }
            }
            error_log("[BTR] Pagina creata o aggiornata con ID: " . ($pagina_id ?: $new_page_id));
        } else {
            // Se la pagina pubblica è stata disattivata, mettila in bozza
            $pagina_id = get_post_meta($post_id, 'btr_pagina_pubblica_id', true);
            if ($pagina_id && get_post_status($pagina_id) !== false) {
                wp_update_post(array(
                    'ID' => $pagina_id,
                    'post_status' => 'draft',
                ));
                error_log("[BTR] Pagina pubblica disabilitata e impostata come bozza: ID {$pagina_id}");
            }
        }
        // Salva badge dinamici personalizzati
        if (!empty($_POST['btr_badge_rules']) && is_array($_POST['btr_badge_rules'])) {
            $badge_rules = array_values(array_filter($_POST['btr_badge_rules'], function ($rule) {
                return isset($rule['soglia']) && $rule['soglia'] !== '';
            }));

            foreach ($badge_rules as &$rule) {
                $rule['soglia'] = intval($rule['soglia'] ?? 0);
                $rule['label'] = sanitize_text_field($rule['label'] ?? '');
                $rule['class'] = sanitize_text_field($rule['class'] ?? '');
                $rule['condizione'] = in_array($rule['condizione'], ['eq', 'lt', 'lte'], true) ? $rule['condizione'] : 'lte';
                $rule['enabled'] = array_key_exists('enabled', $rule) && in_array($rule['enabled'], ['1', 1, true, 'true'], true) ? '1' : '0';
            }

            update_post_meta($post_id, 'btr_badge_rules', $badge_rules);
            error_log("[SAVE META] Regole badge salvate: " . print_r($badge_rules, true));
        } else {
            delete_post_meta($post_id, 'btr_badge_rules');
            error_log("[SAVE META] Regole badge rimosse.");
        }

        // Recupera tutti i metadati salvati per la sincronizzazione
        $meta_values = get_post_meta($post_id, '', true);
        // Emetti l'azione per sincronizzare con WooCommerce
        do_action('btr_sync_with_woocommerce', $post_id, $meta_values);
    }


    /**
     * Elimina tutti i prodotti e metadati associati a un pacchetto quando viene eliminato.
     * @param int $post_id
     */
    public function delete_product_on_package_delete($post_id)
    {
        // Esempio: elimina prodotti WooCommerce associati al pacchetto
        $product_id = get_post_meta($post_id, 'btr_wc_product_id', true);
        if ($product_id) {
            wp_delete_post($product_id, true);
            error_log("[DELETE] Prodotto WooCommerce ID {$product_id} eliminato per pacchetto ID {$post_id}");
            delete_post_meta($post_id, 'btr_wc_product_id');
        }
        // Rimuove tutti i metadati collegati al pacchetto
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $key => $values) {
            delete_post_meta($post_id, $key);
            error_log("[DELETE] Metadato '{$key}' eliminato per pacchetto ID {$post_id}");
        }

        // Procedi solo se è un pacchetto
        if (get_post_type($post_id) !== 'btr_pacchetti') {
            return;
        }

        // Elimina la pagina pubblica generata per il pacchetto, se esiste
        $pagina_pubblica_id = get_post_meta($post_id, 'btr_pagina_pubblica_id', true);
        if ($pagina_pubblica_id && get_post_status($pagina_pubblica_id)) {
            wp_delete_post($pagina_pubblica_id, true);
            error_log("[DELETE] Pagina pubblica ID {$pagina_pubblica_id} eliminata per pacchetto ID {$post_id}");
        }
    }



    /**
     * Carica script e stili necessari nell'admin
     */
    public function enqueue_admin_assets($hook)
    {
        // Verifica che lo schermo corrente sia definito
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        // Verifica se siamo nel tipo di post 'btr_pacchetti'
        if ('btr_pacchetti' === $screen->post_type) {
            // Carica script e stili necessari
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('jquery-ui-tooltip');
            wp_enqueue_script('jquery-validate', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js', array('jquery'), '1.19.5', true);
            wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
            wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
            
            // CSS e JS del plugin
            wp_enqueue_style('btr-admin-css', BTR_PLUGIN_URL . 'assets/css/btr-admin.css', array(), BTR_VERSION);
            wp_enqueue_script('btr-admin-js', BTR_PLUGIN_URL . 'assets/js/btr-admin.js', array('jquery', 'jquery-ui-sortable', 'jquery-ui-tooltip', 'jquery-validate', 'select2'), BTR_VERSION, true);
            
            // CSS e JS di debug per fix validazione admin
            wp_enqueue_style('btr-admin-debug-css', BTR_PLUGIN_URL . 'assets/css/admin-debug-styles.css', array('btr-admin-css'), BTR_VERSION);
            wp_enqueue_script('btr-admin-debug-fix', BTR_PLUGIN_URL . 'assets/js/admin-debug-fix.js', array('jquery', 'btr-admin-js'), BTR_VERSION, true);
            
            // JS per la gestione del pricing mode con AJAX
            wp_enqueue_script('btr-admin-pricing-mode', BTR_PLUGIN_URL . 'assets/js/admin-pricing-mode.js', array('jquery'), BTR_VERSION, true);
            
            // JS per la gestione dinamica dei costi extra con campi hidden
            // Questo script deve essere caricato DOPO btr-admin.js per sovrascrivere il template dei costi extra
            wp_enqueue_script('btr-admin-extra-costs', BTR_PLUGIN_URL . 'assets/js/admin-extra-costs-dynamic.js', array('jquery', 'jquery-ui-sortable', 'btr-admin-js'), BTR_VERSION, true);
            
            // Localizza lo script per passare le variabili di JavaScript
            wp_localize_script('btr-admin-js', 'btrAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                // Altre variabili JS possono essere aggiunte qui
            ));
            
            // Localizza lo script del pricing mode
            wp_localize_script('btr-admin-pricing-mode', 'btrPricingMode', array(
                'nonce' => wp_create_nonce('btr_pricing_mode_nonce'),
                'ajax_url' => admin_url('admin-ajax.php'),
            ));
        }
    }

    /**
     * Verifica se una pagina esiste già con uno slug specifico
     */
    private function post_exists_by_slug($slug)
    {
        global $wpdb;
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' LIMIT 1";
        $post_id = $wpdb->get_var($wpdb->prepare($sql, $slug));
        return $post_id ? true : false;
    }
    /**
     * Restituisce la giacenza originale e attuale di una variazione (camera).
     *
     * @param int $variation_id
     * @return array
     */
    public static function get_room_stock_info($variation_id): array {
        if (!$variation_id) {
            return ['originale' => null, 'attuale' => null];
        }

        $giacenza_originale = get_post_meta($variation_id, '_btr_giacenza_origine', true);
        if ($giacenza_originale === '') {
            $stock = get_post_meta($variation_id, '_stock', true);
            if ($stock !== '') {
                update_post_meta($variation_id, '_btr_giacenza_origine', $stock);
                $giacenza_originale = $stock;
            }
        }

        $giacenza_attuale = get_post_meta($variation_id, '_stock', true);

        return [
            'originale' => $giacenza_originale,
            'attuale'   => $giacenza_attuale,
        ];
    }

    /**
     * Funzione di fallback per diagnosticare i problemi di salvataggio
     */
    public function save_pacchetti_meta_fallback($post_id, $post)
    {
        // Logga TUTTI i salvataggi per debug
        error_log("FALLBACK SAVE: Post ID: $post_id, Post Type: " . get_post_type($post_id));
        
        // Se è il nostro post type, prova a salvare
        if (get_post_type($post_id) === 'btr_pacchetti') {
            error_log("FALLBACK: Questo è un pacchetto BTR, chiamiamo la funzione di salvataggio principale");
            $this->save_pacchetti_meta($post_id, $post);
        }
    }

    /**
     * Handler AJAX per salvare il pricing mode
     */
    public function ajax_save_pricing_mode()
    {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'btr_pricing_mode_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }

        // Verifica permessi
        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }

        // Verifica che sia il nostro post type
        if (get_post_type($post_id) !== 'btr_pacchetti') {
            wp_send_json_error('Tipo di post non valido');
            return;
        }

        $data_key = sanitize_text_field($_POST['data_key']);
        $pricing_per_room = intval($_POST['pricing_per_room']) === 1;

        error_log("AJAX PRICING MODE: Post ID: $post_id, Data Key: $data_key, Pricing per room: " . ($pricing_per_room ? 'true' : 'false'));

        // Recupera i dati esistenti dell'allotment
        $extra_allotments = get_post_meta($post_id, 'btr_camere_extra_allotment_by_date', true);
        if (!is_array($extra_allotments)) {
            $extra_allotments = array();
        }

        // Aggiorna il campo specifico
        if (!isset($extra_allotments[$data_key])) {
            $extra_allotments[$data_key] = array();
        }

        // Forza il salvataggio come boolean esplicito
        $extra_allotments[$data_key]['pricing_per_room'] = $pricing_per_room ? true : false;

        // Salva i dati aggiornati
        $result = update_post_meta($post_id, 'btr_camere_extra_allotment_by_date', $extra_allotments);

        if ($result !== false) {
            error_log("AJAX PRICING MODE: Salvato con successo");
            wp_send_json_success('Pricing mode salvato correttamente');
        } else {
            error_log("AJAX PRICING MODE: Errore nel salvataggio");
            wp_send_json_error('Errore nel salvataggio');
        }
    }
}

//new BTR_Pacchetti_CPT();
