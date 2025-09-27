<?php
/**
 * Shortcode per la pagina di selezione piano pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Selection_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('btr_payment_selection', [$this, 'render_payment_selection']);
        add_shortcode('btr_payment_links_summary', [$this, 'render_payment_links_summary']);
        // Handler AJAX rimosso - gestito da BTR_Payment_Ajax
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_head', [$this, 'add_resource_hints'], 2);
    }
    
    /**
     * Enqueue CSS and JS assets
     */
    public function enqueue_assets() {
        // Carica CSS per pagine di selezione pagamento
        global $post;
        $should_load = false;
        $load_payment_selection_assets = false;
        $load_payment_links_summary_assets = false;

        // Controlla shortcode presenti nel contenuto del post
        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'btr_payment_selection')) {
                $should_load = true;
                $load_payment_selection_assets = true;
            }

            if (has_shortcode($post->post_content, 'btr_payment_links_summary')) {
                $should_load = true;
                $load_payment_links_summary_assets = true;
            }
        }

        // Controlla URL della pagina
        if (isset($_GET['preventivo_id'])) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

            if (strpos($request_uri, 'selezione-piano-pagamento') !== false ||
                strpos($request_uri, 'payment-selection') !== false) {
                $should_load = true;
                $load_payment_selection_assets = true;
            }

            if (strpos($request_uri, 'payment-links-summary') !== false) {
                $should_load = true;
                $load_payment_links_summary_assets = true;
            }
        }

        if ($should_load) {
            // Carica il design system unificato
            wp_enqueue_style(
                'btr-unified-design-system',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/btr-unified-design-system.css',
                [],
                BTR_VERSION
            );

            if ($load_payment_selection_assets) {
                wp_enqueue_style(
                    'btr-payment-selection-unified',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/payment-selection-unified.css',
                    ['btr-unified-design-system'],
                    BTR_VERSION
                );
            }

            if ($load_payment_links_summary_assets) {
                wp_enqueue_style(
                    'btr-payment-links-summary',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/payment-links-summary.css',
                    ['btr-unified-design-system'],
                    BTR_VERSION
                );
            }

            // JavaScript per interazioni - non serve caricarlo qui perché è già nel template
        }
    }
    
    /**
     * Render shortcode
     */
    public function render_payment_selection($atts) {
        // Inizializza la sessione WooCommerce se necessario
        if (function_exists('WC') && !WC()->session) {
            WC()->initialize_session();
        }
        
        // Inizializza il carrello se necessario
        if (function_exists('WC') && !WC()->cart) {
            wc_load_cart();
        }
        
        // Carica il template basato sullo stile selezionato
        ob_start();
        
        // Usa il template riepilogo/unified con design system
        $template_path = BTR_PLUGIN_DIR . 'templates/payment-selection-page-riepilogo-style.php';

        if (!file_exists($template_path)) {
            $template_path = BTR_PLUGIN_DIR . 'templates/payment-selection-page-unified.php';
        }

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>' . __('Template non trovato.', 'born-to-ride-booking') . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render payment links summary shortcode
     */
    public function render_payment_links_summary($atts) {
        // Carica il template
        ob_start();
        
        $template_path = BTR_PLUGIN_DIR . 'templates/frontend/payment-links-summary.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>' . __('Template riepilogo link non trovato.', 'born-to-ride-booking') . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX payment plan creation
     */
    public function handle_create_payment_plan() {
        // Verifica nonce
        if (!isset($_POST['payment_nonce']) || !wp_verify_nonce($_POST['payment_nonce'], 'btr_payment_plan_nonce')) {
            wp_send_json_error(['message' => __('Errore di sicurezza.', 'born-to-ride-booking')]);
        }
        
        $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
        $payment_plan = isset($_POST['payment_plan']) ? sanitize_text_field($_POST['payment_plan']) : '';
        
        if (!$preventivo_id || !$payment_plan) {
            wp_send_json_error(['message' => __('Dati mancanti.', 'born-to-ride-booking')]);
        }
        
        // Verifica che il preventivo esista
        $preventivo = get_post($preventivo_id);
        if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
            wp_send_json_error(['message' => __('Preventivo non valido.', 'born-to-ride-booking')]);
        }
        
        // Crea il piano di pagamento se la classe esiste
        if (class_exists('BTR_Payment_Plans')) {
            $plan_data = [
                'preventivo_id' => $preventivo_id,
                'plan_type' => $payment_plan,
                'total_amount' => get_post_meta($preventivo_id, '_totale_preventivo', true)
            ];
            
            // Aggiungi dati specifici per tipo di piano
            if ($payment_plan === 'deposit_balance') {
                $plan_data['deposit_percentage'] = isset($_POST['deposit_percentage']) ? intval($_POST['deposit_percentage']) : 30;
            } elseif ($payment_plan === 'group_split') {
                // Processa i partecipanti selezionati per il pagamento di gruppo
                $group_participants = [];
                
                if (isset($_POST['group_participants']) && is_array($_POST['group_participants'])) {
                    foreach ($_POST['group_participants'] as $index => $participant) {
                        if (isset($participant['selected']) && $participant['selected'] == '1') {
                            $group_participants[] = [
                                'index' => intval($index),
                                'name' => sanitize_text_field($participant['name'] ?? ''),
                                'email' => sanitize_email($participant['email'] ?? ''),
                                'shares' => intval($participant['shares'] ?? 1)
                            ];
                        }
                    }
                }
                
                if (empty($group_participants)) {
                    wp_send_json_error(['message' => __('Nessun partecipante selezionato per il pagamento di gruppo.', 'born-to-ride-booking')]);
                }
                
                $plan_data['group_participants'] = $group_participants;
                
                // Salva i dati del gruppo in sessione per uso successivo
                if (WC()->session) {
                    WC()->session->set('btr_group_participants', $group_participants);
                }
            }
            
            // Crea il piano
            $result = BTR_Payment_Plans::create_payment_plan($plan_data);
            
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            
            // Salva il tipo di piano in sessione per uso futuro
            if (WC()->session) {
                WC()->session->set('btr_payment_plan_type', $payment_plan);
                WC()->session->set('btr_payment_plan_created', true);
            }
        }
        
        // Se è un pagamento di gruppo, genera i link di pagamento
        if ($payment_plan === 'group_split' && class_exists('BTR_Group_Payments')) {
            $group_payments = new BTR_Group_Payments();
            
            // Genera i link di pagamento per tutti i partecipanti
            $payment_links = $group_payments->generate_group_payment_links($preventivo_id, 'full');
            
            if (is_wp_error($payment_links)) {
                wp_send_json_error(['message' => 'Errore nella generazione dei link: ' . $payment_links->get_error_message()]);
            }
            
            // Salva i link generati in sessione per visualizzazione successiva
            if (WC()->session) {
                WC()->session->set('btr_generated_payment_links', $payment_links);
                WC()->session->set('btr_payment_preventivo_id', $preventivo_id);
            }
            
            // Salva anche come meta del preventivo per persistenza
            update_post_meta($preventivo_id, '_btr_payment_links_generated', true);
            update_post_meta($preventivo_id, '_btr_payment_links_generated_at', current_time('mysql'));
            
            // Invia email automaticamente se configurato
            if (get_option('btr_auto_send_payment_links', 'yes') === 'yes') {
                $this->send_all_payment_emails($payment_links);
            }
            
            // Redirect alla pagina di riepilogo link invece del checkout
            $links_summary_page = get_option('btr_payment_links_summary_page');
            if ($links_summary_page) {
                $redirect_url = add_query_arg('preventivo_id', $preventivo_id, get_permalink($links_summary_page));
            } else {
                // Se non esiste una pagina dedicata, usa la pagina di conferma standard con parametro speciale
                $redirect_url = add_query_arg([
                    'preventivo_id' => $preventivo_id,
                    'show_payment_links' => 'true'
                ], home_url('/payment-links-summary/'));
            }
        } else {
            // Prepara URL di redirect standard
            $redirect_url = wc_get_checkout_url();
            
            // Se è un pagamento di gruppo ma senza generazione link, usa la pagina gruppo
            if ($payment_plan === 'group_split') {
                $group_page_id = get_option('btr_group_payment_page');
                if ($group_page_id) {
                    $redirect_url = add_query_arg('preventivo_id', $preventivo_id, get_permalink($group_page_id));
                }
            }
        }
        
        wp_send_json_success([
            'redirect_url' => $redirect_url,
            'message' => __('Piano di pagamento creato con successo.', 'born-to-ride-booking')
        ]);
    }
    
    /**
     * Invia email a tutti i partecipanti con i link di pagamento
     */
    private function send_all_payment_emails($payment_links) {
        if (!class_exists('BTR_Group_Payments')) {
            return false;
        }
        
        $group_payments = new BTR_Group_Payments();
        $sent_count = 0;
        
        foreach ($payment_links as $link) {
            if (isset($link['payment_id'])) {
                $result = $group_payments->send_payment_link_email($link['payment_id']);
                if ($result) {
                    $sent_count++;
                }
                
                // Piccola pausa tra gli invii per evitare problemi con il server mail
                usleep(500000); // 0.5 secondi
            }
        }
        
        return $sent_count;
    }
    
    /**
     * Aggiunge resource hints per ottimizzare il caricamento
     */
    public function add_resource_hints() {
        global $post;
        
        // Verifica se siamo nella pagina di selezione pagamento
        $should_preload = false;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'btr_payment_selection')) {
            $should_preload = true;
        }
        
        if (isset($_GET['preventivo_id']) && 
            (strpos($_SERVER['REQUEST_URI'], 'selezione-piano-pagamento') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'payment-selection') !== false)) {
            $should_preload = true;
        }
        
        if ($should_preload) {
            // Preload CSS del design system unificato
            echo '<link rel="preload" href="' . plugin_dir_url(dirname(__FILE__)) . 'assets/css/btr-unified-design-system.css?ver=' . BTR_VERSION . '" as="style">' . "\n";
            
            // Preconnect a domini esterni se necessario
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="dns-prefetch" href="https://fonts.googleapis.com">' . "\n";
            
            // Prefetch della pagina checkout per navigazione più veloce
            if (function_exists('wc_get_checkout_url')) {
                echo '<link rel="prefetch" href="' . wc_get_checkout_url() . '">' . "\n";
            }
        }
    }
}
