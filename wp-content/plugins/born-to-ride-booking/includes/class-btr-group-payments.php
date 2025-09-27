<?php
/**
 * Gestione pagamenti individuali per prenotazioni di gruppo
 * 
 * Gestisce la creazione di link di pagamento personalizzati per ogni partecipante
 * di una prenotazione di gruppo, permettendo pagamenti individuali separati.
 * 
 * @since 1.0.14
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Group_Payments {
    
    /**
     * Durata validità link pagamento in ore
     */
    const PAYMENT_LINK_EXPIRY_HOURS = 72;
    
    /**
     * Prefisso per gli hash dei pagamenti
     */
    const PAYMENT_HASH_PREFIX = 'btr_pay_';

    public function __construct() {
        add_action('init', [$this, 'init_hooks']);
        add_action('template_redirect', [$this, 'handle_payment_page']);
        
        // AJAX endpoints
        add_action('wp_ajax_btr_generate_group_payment_links', [$this, 'ajax_generate_group_payment_links']);
        add_action('wp_ajax_btr_generate_individual_payment_link', [$this, 'ajax_generate_individual_payment_link']);
        add_action('wp_ajax_btr_send_payment_email', [$this, 'ajax_send_payment_email']);
        add_action('wp_ajax_nopriv_btr_process_individual_payment', [$this, 'ajax_process_individual_payment']);
        
        // WooCommerce hooks
        add_action('woocommerce_thankyou', [$this, 'handle_individual_payment_completion'], 10, 1);
        
        // Cleanup expired links
        add_action('btr_cleanup_expired_payment_links', [$this, 'cleanup_expired_links']);
        if (!wp_next_scheduled('btr_cleanup_expired_payment_links')) {
            wp_schedule_event(time(), 'daily', 'btr_cleanup_expired_payment_links');
        }

        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function init_hooks() {
        // Registra le rewrite rules per i link di pagamento
        add_action('init', [$this, 'add_rewrite_rules']);
        
        // Registra custom query vars per il routing
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('parse_request', [$this, 'parse_payment_request']);
    }

    /**
     * Registra le rewrite rules per i link di pagamento
     * 
     * @since 1.0.99
     */
    public function add_rewrite_rules() {
        // Pattern per intercettare /pagamento-gruppo/{hash}/
        // Hash SHA256 = 64 caratteri esadecimali
        add_rewrite_rule(
            '^pagamento-gruppo/([a-f0-9]{64})/?$',
            'index.php?btr_payment_action=individual&btr_payment_hash=$matches[1]',
            'top'
        );

        // Compatibilità con vecchi link /pay-individual/{hash}
        add_rewrite_rule(
            '^pay-individual/([a-f0-9]{64})/?$',
            'index.php?btr_payment_action=individual&btr_payment_hash=$matches[1]',
            'top'
        );
        
        // Pattern alternativo per compatibilità
        add_rewrite_rule(
            '^btr-payment/([a-f0-9]{64})/?$', 
            'index.php?btr_payment_action=individual&btr_payment_hash=$matches[1]',
            'top'
        );
        
        // Pattern per la pagina di riepilogo link di pagamento
        add_rewrite_rule(
            '^payment-links-summary/?$',
            'index.php?btr_payment_action=links_summary',
            'top'
        );
    }

    /**
     * Aggiunge query vars personalizzate
     */
    public function add_query_vars($vars) {
        $vars[] = 'btr_payment_hash';
        $vars[] = 'btr_payment_action';
        return $vars;
    }

    /**
     * Analizza le richieste di pagamento
     * 
     * Con le rewrite rules registrate, questo metodo ora serve come fallback
     * e per debug/logging delle richieste di pagamento
     */
    public function parse_payment_request($wp) {
        // Le query vars dovrebbero già essere impostate dalle rewrite rules
        // Questo serve come fallback e per debug
        if (!empty($wp->query_vars['btr_payment_hash']) && !empty($wp->query_vars['btr_payment_action'])) {
            // Log per debug (solo se BTR_DEBUG è attivo)
            if (defined('BTR_DEBUG') && BTR_DEBUG) {
                btr_debug_log('Payment request intercepted: ' . $wp->query_vars['btr_payment_hash']);
            }
            return;
        }
        
        // Fallback: Intercetta URL come /pagamento-gruppo/{hash} se le rewrite rules non funzionano
        if (isset($wp->request) && preg_match('#^(?:pagamento-gruppo|pay-individual)/([a-f0-9]{64})/?$#i', $wp->request, $matches)) {
            $wp->query_vars['btr_payment_hash'] = $matches[1];
            $wp->query_vars['btr_payment_action'] = 'individual';
            
            if (defined('BTR_DEBUG') && BTR_DEBUG) {
                btr_debug_log('Payment request intercepted via fallback: ' . $matches[1]);
            }
        }
    }

    /**
     * Gestisce la visualizzazione della pagina di pagamento
     * 
     * @since 1.0.99 - Migliorato per supportare le rewrite rules
     */
    public function handle_payment_page() {
        $payment_hash = get_query_var('btr_payment_hash', false);
        $payment_action = get_query_var('btr_payment_action', false);

        // Debug logging
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            btr_debug_log('Handle payment page - Hash: ' . $payment_hash . ', Action: ' . $payment_action);
        }

        if ($payment_hash && $payment_action === 'individual') {
            // Previeni caching della pagina
            nocache_headers();
            
            // Imposta headers corretti
            status_header(200);
            
            // Renderizza la pagina di pagamento
            $this->render_individual_payment_page($payment_hash);
            exit;
        } elseif ($payment_action === 'links_summary') {
            // Gestisci la pagina di riepilogo link
            nocache_headers();
            status_header(200);
            
            // Carica header WordPress
            get_header();
            
            // Renderizza il template di riepilogo
            echo do_shortcode('[btr_payment_links_summary]');
            
            // Carica footer WordPress
            get_footer();
            exit;
        }
    }

    /**
     * Genera link di pagamento individuali per tutti i partecipanti di un preventivo
     * @since 1.0.237 Aggiunto supporto per partecipanti selezionati
     * @since 1.0.238 Aggiunto supporto per importi personalizzati
     */
    public function generate_group_payment_links($preventivo_id, $payment_type = 'full', $selected_participants_data = null) {
        global $wpdb;

        // Recupera TUTTI gli anagrafici dal preventivo
        $all_anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (empty($all_anagrafici) || !is_array($all_anagrafici)) {
            return new WP_Error('no_participants', 'Nessun partecipante trovato nel preventivo');
        }

        // PRICE SNAPSHOT SYSTEM v1.0 - Usa snapshot se disponibile per evitare ricalcoli errati
        $price_snapshot = get_post_meta($preventivo_id, '_price_snapshot', true);
        $has_snapshot = get_post_meta($preventivo_id, '_has_price_snapshot', true);
        
        // Prima scelta: totale consolidato che include assicurazioni ed extra
        $totale_preventivo = get_post_meta($preventivo_id, '_totale_preventivo', true);
        if ($totale_preventivo && floatval($totale_preventivo) > 0) {
            $prezzo_totale = (float) $totale_preventivo;
            error_log('[BTR TOTAL] Group Payments: Usando _totale_preventivo = €' . $prezzo_totale);
        } else if ($has_snapshot && !empty($price_snapshot) && isset($price_snapshot['totals']['grand_total'])) {
            $prezzo_totale = (float) $price_snapshot['totals']['grand_total'];
            error_log('[BTR PRICE SNAPSHOT] Group Payments: Usando totale da snapshot - €' . $prezzo_totale);
        } else {
            // Fallback robusto: somma manuale
            $prezzo_base = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
            $totale_assicurazioni = (float) get_post_meta($preventivo_id, '_totale_assicurazioni', true);
            $totale_costi_extra = (float) get_post_meta($preventivo_id, '_totale_costi_extra', true);
            $prezzo_totale = round($prezzo_base + $totale_assicurazioni + $totale_costi_extra, 2);
            error_log('[BTR LEGACY] Group Payments: Fallback somma manuale = €' . $prezzo_totale . ' (base ' . $prezzo_base . ' + ass ' . $totale_assicurazioni . ' + extra ' . $totale_costi_extra . ')');
        }
        
        // FIX v1.0.238: Gestione partecipanti selezionati CON IMPORTI PERSONALIZZATI
        if ($selected_participants_data !== null && is_array($selected_participants_data) && !empty($selected_participants_data)) {
            $anagrafici = [];
            $participant_amounts = [];
            $total_shares = 0;
            
            // Costruisci array partecipanti e calcola importi personalizzati
            foreach ($selected_participants_data as $data) {
                $index = isset($data['index']) ? intval($data['index']) : intval($data);
                
                if (isset($all_anagrafici[$index])) {
                    $anagrafici[$index] = $all_anagrafici[$index];
                    
                    // Se è fornito un importo specifico, usalo
                    if (isset($data['amount']) && $data['amount'] > 0) {
                        $participant_amounts[$index] = floatval($data['amount']);
                    } 
                    // Altrimenti calcola in base alle quote
                    else if (isset($data['shares'])) {
                        $shares = intval($data['shares']);
                        $total_shares += $shares;
                        $participant_amounts[$index] = ['shares' => $shares]; // Calcoleremo dopo
                    }
                    // Fallback: divisione equa
                    else {
                        $participant_amounts[$index] = null; // Divisione equa
                    }
                }
            }
            
            // Calcola importi basati su quote se necessario
            if ($total_shares > 0) {
                $price_per_share = $prezzo_totale / $total_shares;
                foreach ($participant_amounts as $index => &$amount) {
                    if (is_array($amount) && isset($amount['shares'])) {
                        $amount = $price_per_share * $amount['shares'];
                        btr_debug_log("BTR: Partecipante $index - Quote: {$amount['shares']} - Importo: $amount");
                    }
                }
            }
            
            if (empty($anagrafici)) {
                return new WP_Error('no_valid_participants', 'Nessun partecipante valido tra quelli selezionati');
            }
            
            btr_debug_log('BTR Group Payments: Generazione link per ' . count($anagrafici) . ' partecipanti con importi personalizzati');
            btr_debug_log('BTR: Anagrafici array keys: ' . implode(', ', array_keys($anagrafici)));
            btr_debug_log('BTR: Participant amounts: ' . print_r($participant_amounts, true));
        } else {
            // Usa tutti gli anagrafici con divisione equa (comportamento originale)
            $anagrafici = $all_anagrafici;
            $participant_amounts = [];
            btr_debug_log('BTR Group Payments: Generazione link per TUTTI i ' . count($anagrafici) . ' partecipanti');
        }

        $num_participants = count($anagrafici);
        
        if ($num_participants === 0) {
            return new WP_Error('division_by_zero', 'Numero partecipanti non valido');
        }

        // FIX v1.0.239: Trova email di riferimento (primo partecipante con email)
        $reference_email = '';
        foreach ($all_anagrafici as $person) {
            if (!empty($person['email'])) {
                $reference_email = $person['email'];
                break;
            }
        }
        
        if (empty($reference_email)) {
            return new WP_Error('no_email', 'Nessuna email trovata per i partecipanti');
        }

        // Importo di default se non specificato
        $default_amount = $prezzo_totale / $num_participants;
        $links = [];
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $table_links = $wpdb->prefix . 'btr_payment_links';

        // TRANSACTION START v1.0.241: Transazione atomica per generazione link gruppo
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($anagrafici as $index => $participant) {
            $participant_name = trim(($participant['nome'] ?? '') . ' ' . ($participant['cognome'] ?? ''));
            $participant_email = $participant['email'] ?? '';

            // FIX v1.0.239: Usa email di riferimento se il partecipante non ha email
            if (empty($participant_email)) {
                $participant_email = $reference_email;
                btr_debug_log("BTR: Usando email di riferimento per $participant_name: $reference_email");
            }

            // FIX v1.0.238: Usa importo personalizzato se disponibile, altrimenti default
            $participant_amount = isset($participant_amounts[$index]) && $participant_amounts[$index] !== null 
                ? floatval($participant_amounts[$index]) 
                : $default_amount;
            
            btr_debug_log("BTR: Generazione link per $participant_name - Importo: €" . number_format($participant_amount, 2, ',', '.'));

            // Genera hash sicuro per il pagamento
            $payment_hash = $this->generate_secure_hash($preventivo_id, $index, $participant_email);
            
            // Inserisci record pagamento
            $payment_data = [
                'preventivo_id' => $preventivo_id,
                'participant_index' => $index,
                'participant_name' => $participant_name,
                'participant_email' => $participant_email,
                'amount' => $participant_amount,
                'payment_status' => 'pending',
                'payment_type' => $payment_type,
                'payment_hash' => $payment_hash,
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::PAYMENT_LINK_EXPIRY_HOURS . ' hours'))
            ];

            $result = $wpdb->insert($table_payments, $payment_data);
            
            if ($result === false) {
                throw new Exception("Errore inserimento pagamento per $participant_name: " . $wpdb->last_error);
            }

            $payment_id = $wpdb->insert_id;

            // Genera hash per il link
            $link_hash = $this->generate_link_hash($payment_id, $payment_hash);
            
            // Inserisci link di pagamento
            $link_data = [
                'payment_id' => $payment_id,
                'link_hash' => $link_hash,
                'link_type' => 'individual',
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::PAYMENT_LINK_EXPIRY_HOURS . ' hours'))
            ];

            $result_links = $wpdb->insert($table_links, $link_data);
            
            if ($result_links === false) {
                throw new Exception("Errore inserimento link per $participant_name: " . $wpdb->last_error);
            }

            $links[] = [
                'payment_id' => $payment_id,
                'participant_name' => $participant_name,
                'participant_email' => $participant_email,
                'amount' => $participant_amount,
                'payment_url' => home_url('/pagamento-gruppo/' . $link_hash),
                'payment_hash' => $payment_hash,
                'payment_status' => 'pending',
                'expires_at' => $link_data['expires_at']
            ];
            }
            
            // TRANSACTION COMMIT v1.0.241: Tutti i link generati con successo
            $wpdb->query('COMMIT');
            btr_debug_log('[BTR Group Payments] Transazione completata con successo per ' . count($links) . ' link di pagamento');
            
        } catch (Exception $e) {
            // TRANSACTION ROLLBACK v1.0.241: Errore durante la generazione
            $wpdb->query('ROLLBACK');
            btr_debug_log('[BTR Group Payments] Transazione fallita in generate_group_payment_links: ' . $e->getMessage());
            return new WP_Error('transaction_failed', 'Errore durante la generazione dei link di pagamento: ' . $e->getMessage());
        }

        return $links;    }

    /**
     * Genera hash sicuro per il pagamento
     */
    private function generate_secure_hash($preventivo_id, $participant_index, $email) {
        $data = $preventivo_id . '|' . $participant_index . '|' . $email . '|' . time() . '|' . wp_generate_password(32, false);
        return hash('sha256', $data);
    }

    /**
     * Genera hash per il link di pagamento
     */
    private function generate_link_hash($payment_id, $payment_hash) {
        $data = $payment_id . '|' . $payment_hash . '|' . time() . '|' . wp_generate_password(32, false);
        return hash('sha256', $data);
    }

    /**
     * Collega l'ordine organizzatore ai pagamenti del gruppo
     * 
     * Aggiorna tutti i record dei pagamenti con l'ID dell'ordine organizzatore
     * per tracciare il collegamento tra ordine principale e quote individuali
     * 
     * @param int $order_id ID ordine WooCommerce dell'organizzatore
     * @param int $preventivo_id ID del preventivo
     * @return int Numero di record aggiornati
     * @since 1.0.240
     */
    public function link_organizer_order_to_payments($order_id, $preventivo_id) {
        global $wpdb;
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        
        btr_debug_log('BTR Group Payments: Collegamento ordine ' . $order_id . ' ai pagamenti del preventivo ' . $preventivo_id);
        
        // TRANSACTION START v1.0.241: Collegamento ordine-pagamenti atomico
        $wpdb->query('START TRANSACTION');
        
        try {        
        // Aggiorna tutti i pagamenti del gruppo con l'ID dell'ordine organizzatore
        $updated = $wpdb->update(
            $table_payments,
            ['organizer_order_id' => $order_id],
            ['preventivo_id' => $preventivo_id],
            ['%d'],
            ['%d']
        );
        
        if ($updated === false) {
            btr_debug_log('BTR Group Payments Error: Errore nel collegamento ordine-pagamenti: ' . $wpdb->last_error);
            $wpdb->query('ROLLBACK');
            throw new Exception('Errore nel collegamento ordine-pagamenti: '. $wpdb->last_error);
        }
        
        btr_debug_log('BTR Group Payments: Collegati ' . $updated . ' pagamenti all\'ordine ' . $order_id);
        
        // Salva anche il collegamento nel preventivo per riferimento rapido
        update_post_meta($preventivo_id, '_btr_organizer_order_id', $order_id);
        update_post_meta($preventivo_id, '_btr_group_payment_order_linked', true);
        
        // Verifica che i metadata siano stati salvati correttamente
        $order_id_saved = get_post_meta($preventivo_id, '_btr_organizer_order_id', true);
        $linked_flag = get_post_meta($preventivo_id, '_btr_group_payment_order_linked', true);
        
        if (empty($order_id_saved) || !$linked_flag) {
            throw new Exception('Errore nel salvataggio metadata preventivo');
        }
        
        // TRANSACTION COMMIT v1.0.241: Collegamento completato con successo
        $wpdb->query('COMMIT');
        btr_debug_log('[BTR Group Payments] Transazione collegamento completata: '. $updated .'. pagamenti collegati al order_id '. $order_id);
        
        } catch (Exception $e) {
            // TRANSACTION ROLLBACK v1.0.241: Errore durante collegamento
            $wpdb->query('ROLLBACK');
            btr_debug_log('[BTR Group Payments] Transazione collegamento fallita in link_organizer_order_to_payments: '. $e->getMessage());
            return 0;
        }        
        // Trigger evento per altre azioni
        do_action('btr_organizer_order_linked', $order_id, $preventivo_id, $updated);
        
        return $updated;
    }

    /**
     * Verifica se tutti i pagamenti sono completati e aggiorna l'ordine organizzatore
     * 
     * @param int $preventivo_id ID del preventivo
     * @since 1.0.240
     */
    public function check_and_update_organizer_order_status($preventivo_id) {
        global $wpdb;
        
        btr_debug_log('BTR Group Payments: Verifica completamento pagamenti per preventivo ' . $preventivo_id);
        
        // Recupera statistiche pagamenti
        $stats = $this->get_payment_stats($preventivo_id);
        
        if (!$stats) {
            btr_debug_log('BTR Group Payments: Impossibile recuperare statistiche pagamenti');
            return false;
        }
        
        btr_debug_log('BTR Group Payments: Statistiche - Totale: ' . $stats['total_payments'] . ', Pagati: ' . $stats['paid_count']);
        
        // Verifica se tutti hanno pagato
        if ($stats['total_payments'] > 0 && $stats['paid_count'] === $stats['total_payments']) {
            btr_debug_log('BTR Group Payments: Tutti i pagamenti completati! Aggiorno ordine organizzatore.');
            
            // Recupera l'ordine organizzatore
            $organizer_order_id = get_post_meta($preventivo_id, '_btr_organizer_order_id', true);
            
            if (!$organizer_order_id) {
                btr_debug_log('BTR Group Payments Error: Ordine organizzatore non trovato per preventivo ' . $preventivo_id);
                return false;
            }
            
            $order = wc_get_order($organizer_order_id);
            if (!$order) {
                btr_debug_log('BTR Group Payments Error: Ordine WooCommerce non valido: ' . $organizer_order_id);
                return false;
            }
            
            // Aggiorna stato ordine
            $order->set_status('wc-completed', __('Tutti i pagamenti del gruppo sono stati completati', 'born-to-ride-booking'));
            $order->save();
            
            // Aggiungi nota all''ordine
            $order->add_order_note(sprintf(
                __('Pagamenti gruppo completati: %d su %d partecipanti hanno pagato. Totale raccolto: €%s', 'born-to-ride-booking'),
                $stats['paid_count'],
                $stats['total_payments'],
                number_format($stats['paid_amount'], 2, ',', '.')
            ));
            
            // Aggiorna meta preventivo
            update_post_meta($preventivo_id, '_btr_group_payment_status', 'completed');
            update_post_meta($preventivo_id, '_btr_group_payment_completed_date', current_time('mysql'));
            
            // Trigger evento
            do_action('btr_group_payment_completed', $preventivo_id, $organizer_order_id);
            
            // Invia notifica finale all'organizzatore
            $this->send_group_payment_completed_notification($preventivo_id, $organizer_order_id, $stats);
            
            btr_debug_log('BTR Group Payments: Ordine organizzatore aggiornato con successo');
            
            return true;
        } else {
            btr_debug_log('BTR Group Payments: Non tutti hanno ancora pagato (' . $stats['paid_count'] . '/' . $stats['total_payments'] . ')');
        }
        
        return false;
    }
    
    /**
     * Invia notifica finale quando tutti hanno pagato
     * 
     * @param int $preventivo_id
     * @param int $organizer_order_id
     * @param array $stats
     */
    private function send_group_payment_completed_notification($preventivo_id, $organizer_order_id, $stats) {
        $order = wc_get_order($organizer_order_id);
        if (!$order) {
            return;
        }
        
        $organizer_email = $order->get_billing_email();
        if (!$organizer_email) {
            return;
        }
        
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        $subject = sprintf(
            __('🎉 Pagamento Gruppo Completato - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        $message = sprintf(
            __("Ottima notizia!\n\nTutti i partecipanti hanno completato il pagamento per il viaggio:\n\n%s\n\nRiepilogo:\n- Partecipanti totali: %d\n- Importo totale raccolto: €%s\n\nOra puoi procedere con l'organizzazione del viaggio.\n\nGrazie per aver scelto %s!", 'born-to-ride-booking'),
            $package_title,
            $stats['total_payments'],
            number_format($stats['paid_amount'], 2, ',', '.'),
            get_bloginfo('name')
        );
        
        // Usa BTR_Payment_Email_Manager se disponibile
        if (class_exists('BTR_Payment_Email_Manager')) {
            $email_manager = BTR_Payment_Email_Manager::get_instance();
            $email_manager->send_email($organizer_email, $subject, nl2br($message));
        } else {
            // Fallback a wp_mail
            wp_mail($organizer_email, $subject, $message);
        }
        
        btr_debug_log('BTR Group Payments: Notifica completamento inviata a organizzatore');
    }

    /**
     * Renderizza la pagina di pagamento individuale
     */
    private function render_individual_payment_page($link_hash) {
        global $wpdb;

        // Verifica validità del link
        $link_data = $this->get_payment_link_data($link_hash);
        
        if (!$link_data) {
            wp_die('Link di pagamento non valido o scaduto.', 'Errore Pagamento', ['response' => 404]);
        }

        // Aggiorna contatore accessi
        $this->update_link_access($link_data['link_id']);

        // Carica template di pagamento
        $this->load_payment_template($link_data);
    }

    /**
     * Recupera i dati del link di pagamento
     */
    private function get_payment_link_data($link_hash) {
        global $wpdb;

        $table_links = $wpdb->prefix . 'btr_payment_links';
        $table_payments = $wpdb->prefix . 'btr_group_payments';

        $sql = $wpdb->prepare("
            SELECT l.*, p.* 
            FROM {$table_links} l
            INNER JOIN {$table_payments} p ON l.payment_id = p.payment_id
            WHERE l.link_hash = %s 
            AND l.is_active = 1 
            AND l.expires_at > NOW()
            AND p.payment_status = 'pending'
        ", $link_hash);

        return $wpdb->get_row($sql, ARRAY_A);
    }

    /**
     * Aggiorna il contatore di accessi al link
     */
    private function update_link_access($link_id) {
        global $wpdb;

        $table_links = $wpdb->prefix . 'btr_payment_links';

        // Aggiorna last_access_at
        $wpdb->update(
            $table_links,
            [
                'last_access_at' => current_time('mysql')
            ],
            ['link_id' => $link_id]
        );

        // Incrementa il contatore in modo atomico
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_links} SET access_count = access_count + 1 WHERE link_id = %d",
            $link_id
        ));
    }

    /**
     * Carica il template moderno del pagamento individuale
     */
    private function load_payment_template($payment_data) {
        // Espone i dati del link al template (eventuale debug o ottimizzazioni future)
        set_query_var('btr_payment_link_data', $payment_data);
        set_query_var('hash', $payment_data['payment_hash']);

        $template = BTR_PLUGIN_DIR . 'templates/frontend/checkout-group-payment.php';

        if (file_exists($template)) {
            include $template;
        } else {
            // Fallback: evita pagina bianca in caso di template mancante
            wp_die(__('Template di pagamento non disponibile.', 'born-to-ride-booking'));
        }
    }
    public function ajax_generate_group_payment_links() {
        check_ajax_referer('btr_group_payments', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Autorizzazione negata');
        }

        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $payment_type = sanitize_text_field($_POST['payment_type'] ?? 'full');

        if (!$preventivo_id) {
            wp_send_json_error('ID preventivo non valido');
        }

        $links = $this->generate_group_payment_links($preventivo_id, $payment_type);

        if (is_wp_error($links)) {
            wp_send_json_error($links->get_error_message());
        }

        wp_send_json_success([
            'links' => $links,
            'message' => 'Link di pagamento generati con successo'
        ]);
    }

    /**
     * Gestisce il completamento del pagamento individuale
     */
    public function handle_individual_payment_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Verifica se è un pagamento individuale
        $payment_hash = $order->get_meta('_btr_payment_hash');
        if (!$payment_hash) {
            return;
        }

        // Aggiorna status pagamento
        $this->update_payment_status($payment_hash, 'paid', $order_id);
        
        // Invia notifiche
        $this->send_payment_confirmation($payment_hash, $order_id);
    }

    /**
     * Aggiorna lo status del pagamento
     */
    private function update_payment_status($payment_hash, $status, $order_id = null) {
        global $wpdb;
        // TRANSACTION START v1.0.241: Aggiornamento status atomico
        $wpdb->query('START TRANSACTION');
        
        try {        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        
        // Prima recupera il preventivo_id per il check successivo
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT preventivo_id FROM {$table_payments} WHERE payment_hash = %s",
            $payment_hash
        ));
        
        // Verifica che il pagamento esista
        if (!$payment) {
            throw new Exception("Pagamento non trovato per hash: $payment_hash");
        }
        
        $update_data = [
            'payment_status' => $status,
            'paid_at' => current_time('mysql')
        ];
        
        if ($order_id) {
            $update_data['wc_order_id'] = $order_id;
        }
        
        
        // Verifica successo update
        if ($wpdb->update(
            $table_payments,
            $update_data,
            ["payment_hash" => $payment_hash]
        ) === false) {
            throw new Exception("Errore aggiornamento status pagamento: " . $wpdb->last_error);
        }
        
        // Se lo stato è 'paid', verifica se tutti hanno pagato
        if ($status === 'paid' && $payment) {
            $this->check_and_update_organizer_order_status($payment->preventivo_id);
        }
        
        // TRANSACTION COMMIT v1.0.241: Status pagamento aggiornato con successo
        $wpdb->query('COMMIT');
        btr_debug_log('[BTR Group Payments] Transazione completata con successo - status aggiornato a: ' . $status);
        
    } catch (Exception $e) {
        // TRANSACTION ROLLBACK v1.0.241: Errore durante aggiornamento status
        $wpdb->query('ROLLBACK');
        btr_debug_log('[BTR Group Payments] Transazione fallita in update_payment_status: ' . $e->getMessage());
        return false;
    }
    }

    /**
     * Invia email di conferma pagamento
     */
    private function send_payment_confirmation($payment_hash, $order_id) {
        global $wpdb;
        
        // Recupera dati pagamento dal hash
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT payment_id FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
            $payment_hash
        ));
        
        if (!$payment) {
            return false;
        }
        
        // Utilizza il payment email manager per inviare la conferma
        if (class_exists('BTR_Payment_Email_Manager')) {
            $email_manager = BTR_Payment_Email_Manager::get_instance();
            
            // Trigger l'azione che invierà l'email
            do_action('btr_payment_completed', $payment->payment_id, $order_id);
            
            return true;
        }
        
        return false;
    }

    /**
     * Pulisce i link di pagamento scaduti
     */
    public function cleanup_expired_links() {
        global $wpdb;

        $table_links = $wpdb->prefix . 'btr_payment_links';
        $table_payments = $wpdb->prefix . 'btr_group_payments';

        // Disattiva link scaduti
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_links} 
            SET is_active = %d 
            WHERE expires_at < NOW() AND is_active = %d",
            0, 1
        ));

        // Marca come scaduti i pagamenti non completati
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_payments} 
            SET payment_status = %s 
            WHERE expires_at < NOW() AND payment_status = %s",
            'expired', 'pending'
        ));
    }

    /**
     * Ottieni statistiche pagamenti per un preventivo
     */
    public function get_payment_stats($preventivo_id) {
        global $wpdb;
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                SUM(amount) as total_amount
            FROM {$table_payments}
            WHERE preventivo_id = %d
        ", $preventivo_id), ARRAY_A);

        return $stats;
    }

    /**
     * AJAX: Genera link di pagamento per singolo partecipante
     */
    public function ajax_generate_individual_payment_link() {
        check_ajax_referer('btr_group_payments', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Autorizzazione negata');
        }

        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $participant_index = intval($_POST['participant_index'] ?? -1);

        if (!$preventivo_id || $participant_index < 0) {
            wp_send_json_error('Dati non validi');
        }

        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (empty($anagrafici[$participant_index])) {
            wp_send_json_error('Partecipante non trovato');
        }

        // Genera link solo per questo partecipante
        $result = $this->generate_individual_payment_link($preventivo_id, $participant_index);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Invia email automaticamente
        $email_sent = $this->send_payment_link_email($result['payment_id']);

        wp_send_json_success([
            'link' => $result,
            'email_sent' => $email_sent,
            'message' => 'Link di pagamento generato e email inviata con successo'
        ]);
    }

    /**
     * Genera link di pagamento per un singolo partecipante
     */
    public function generate_individual_payment_link($preventivo_id, $participant_index) {
        global $wpdb;

        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (empty($anagrafici[$participant_index])) {
            return new WP_Error('participant_not_found', 'Partecipante non trovato');
        }

        $participant = $anagrafici[$participant_index];
        $participant_name = trim(($participant['nome'] ?? '') . ' ' . ($participant['cognome'] ?? ''));
        $participant_email = $participant['email'] ?? '';

        if (empty($participant_email)) {
            return new WP_Error('no_email', 'Email del partecipante non trovata');
        }

        // PRICE SNAPSHOT SYSTEM v1.0 - Usa snapshot se disponibile per evitare ricalcoli errati
        $price_snapshot = get_post_meta($preventivo_id, '_price_snapshot', true);
        $has_snapshot = get_post_meta($preventivo_id, '_has_price_snapshot', true);
        
        if ($has_snapshot && !empty($price_snapshot) && isset($price_snapshot['totals']['grand_total'])) {
            $prezzo_totale = (float) $price_snapshot['totals']['grand_total'];
            error_log('[BTR PRICE SNAPSHOT] Group Payments: Usando totale da snapshot - €' . $prezzo_totale);
        } else {
            // Fallback al metodo legacy
            $prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
            error_log('[BTR LEGACY] Group Payments: Usando totale legacy - €' . $prezzo_totale);
        }
        $num_participants = count($anagrafici);
        $amount_per_person = $prezzo_totale / $num_participants;

        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $table_links = $wpdb->prefix . 'btr_payment_links';

        // Verifica se esiste già un pagamento pendente
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT payment_id FROM {$table_payments} 
             WHERE preventivo_id = %d AND participant_index = %d 
             AND payment_status = 'pending' AND expires_at > NOW()",
            $preventivo_id, $participant_index
        ));

        if ($existing) {
            return new WP_Error('payment_exists', 'Esiste già un pagamento pendente per questo partecipante');
        }

        // Genera hash sicuro
        $payment_hash = $this->generate_secure_hash($preventivo_id, $participant_index, $participant_email);
        
        // Inserisci record pagamento
        $payment_data = [
            'preventivo_id' => $preventivo_id,
            'participant_index' => $participant_index,
            'participant_name' => $participant_name,
            'participant_email' => $participant_email,
            'amount' => $amount_per_person,
            'payment_status' => 'pending',
            'payment_type' => 'full',
            'payment_hash' => $payment_hash,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::PAYMENT_LINK_EXPIRY_HOURS . ' hours'))
        ];

        $result = $wpdb->insert($table_payments, $payment_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Errore nella creazione del pagamento');
        }

        $payment_id = $wpdb->insert_id;

        // Genera hash per il link
        $link_hash = $this->generate_link_hash($payment_id, $payment_hash);
        
        // Inserisci link di pagamento
        $link_data = [
            'payment_id' => $payment_id,
            'link_hash' => $link_hash,
            'link_type' => 'individual',
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::PAYMENT_LINK_EXPIRY_HOURS . ' hours'))
        ];

        $result_links = $wpdb->insert($table_links, $link_data);
            
            if ($result_links === false) {
                throw new Exception("Errore inserimento link per $participant_name: " . $wpdb->last_error);
            }

        return [
            'payment_id' => $payment_id,
            'participant_name' => $participant_name,
            'participant_email' => $participant_email,
            'amount' => $amount_per_person,
            'payment_url' => home_url('/pagamento-gruppo/' . $link_hash),
            'expires_at' => $link_data['expires_at']
        ];
    }

    /**
     * AJAX: Invia email di pagamento
     */
    public function ajax_send_payment_email() {
        check_ajax_referer('btr_group_payments', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Autorizzazione negata');
        }

        $payment_id = intval($_POST['payment_id'] ?? 0);

        if (!$payment_id) {
            wp_send_json_error('ID pagamento non valido');
        }

        $result = $this->send_payment_link_email($payment_id);

        if ($result) {
            wp_send_json_success('Email inviata con successo');
        } else {
            wp_send_json_error('Errore nell\'invio dell\'email');
        }
    }

    /**
     * Invia email con link di pagamento
     */
    public function send_payment_link_email($payment_id) {
        global $wpdb;

        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $table_links = $wpdb->prefix . 'btr_payment_links';

        // Ottieni dati pagamento e link
        $payment_data = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, l.link_hash 
             FROM {$table_payments} p
             LEFT JOIN {$table_links} l ON p.payment_id = l.payment_id
             WHERE p.payment_id = %d AND l.is_active = 1",
            $payment_id
        ), ARRAY_A);

        if (!$payment_data) {
            return false;
        }

        // Ottieni dati preventivo
        $preventivo_id = $payment_data['preventivo_id'];
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
        $date_range = get_post_meta($preventivo_id, '_date_ranges', true);

        $payment_url = home_url('/pagamento-gruppo/' . $payment_data['link_hash']);
        $expires_date = date('d/m/Y H:i', strtotime($payment_data['expires_at']));

        // Componi email
        $to = $payment_data['participant_email'];
        $subject = 'Link per il pagamento individuale - ' . $nome_pacchetto;
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #0097c5;'>Pagamento Individuale</h2>
                
                <p>Ciao <strong>{$payment_data['participant_name']}</strong>,</p>
                
                <p>È stato generato il tuo link personalizzato per il pagamento della quota individuale per:</p>
                
                <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #0097c5; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px;'>{$nome_pacchetto}</h3>
                    " . ($date_range ? "<p><strong>Date:</strong> {$date_range}</p>" : "") . "
                    <p><strong>Tua quota:</strong> €" . number_format($payment_data['amount'], 2, ',', '.') . "</p>
                </div>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$payment_url}' 
                       style='background: #0097c5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                        Procedi al Pagamento
                    </a>
                </p>
                
                <div style='background: #fff3cd; padding: 10px; border-radius: 4px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>⚠️ Importante:</strong> Questo link scade il <strong>{$expires_date}</strong></p>
                </div>
                
                <hr style='margin: 30px 0; border: none; height: 1px; background: #ddd;'>
                
                <p style='font-size: 14px; color: #666;'>
                    Se hai problemi con il link, contattaci direttamente.<br>
                    Questo è un messaggio automatico, non rispondere a questa email.
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Aggiunge menu admin per gestione pagamenti
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Pagamenti di Gruppo',
            'Pagamenti di Gruppo',
            'edit_posts',
            'btr-group-payments',
            [$this, 'admin_page']
        );
    }

    /**
     * Renderizza la pagina admin
     */
    public function admin_page() {
        $preventivo_id = intval($_GET['preventivo_id'] ?? 0);
        
        if ($preventivo_id) {
            include BTR_PLUGIN_DIR . 'admin/views/group-payments.php';
        } else {
            echo '<div class="wrap"><h1>Seleziona un preventivo per gestire i pagamenti di gruppo.</h1></div>';
        }
    }

    // ========================================
    // LAYER VALIDAZIONE INPUT v1.0.241
    // Validazione dati prima delle transazioni
    // ========================================

    /**
     * Valida preventivo ID prima delle operazioni transazionali
     * @param int $preventivo_id ID del preventivo
     * @return bool|WP_Error true se valido, WP_Error se invalido
     */
    private function validate_preventivo_id($preventivo_id) {
        if (empty($preventivo_id) || !is_numeric($preventivo_id) || $preventivo_id <= 0) {
            return new WP_Error('invalid_preventivo_id', 'ID preventivo non valido: deve essere un numero positivo');
        }
        
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE ID = %d AND post_type = 'btr_preventivi'",
            $preventivo_id
        ));
        
        if (!$exists) {
            return new WP_Error('preventivo_not_found', 'Preventivo non trovato nel database');
        }
        
        return true;
    }

    /**
     * Valida dati di pagamento prima delle transazioni
     * @param array $payment_data Dati del pagamento
     * @return bool|WP_Error true se valido, WP_Error se invalido
     */
    private function validate_payment_data($payment_data) {
        $required_fields = ['payment_hash', 'preventivo_id', 'payment_type', 'amount', 'payment_status'];
        
        foreach ($required_fields as $field) {
            if (!isset($payment_data[$field]) || empty($payment_data[$field])) {
                return new WP_Error('missing_payment_field', "Campo obbligatorio mancante: $field");
            }
        }
        
        // Valida hash univoco
        if (strlen($payment_data['payment_hash']) !== 64) {
            return new WP_Error('invalid_payment_hash', 'Hash pagamento deve essere 64 caratteri');
        }
        
        // Valida importo
        if (!is_numeric($payment_data['amount']) || $payment_data['amount'] <= 0) {
            return new WP_Error('invalid_amount', 'Importo deve essere un numero positivo');
        }
        
        // Valida status
        $valid_statuses = ['pending', 'paid', 'failed', 'expired'];
        if (!in_array($payment_data['payment_status'], $valid_statuses)) {
            return new WP_Error('invalid_status', 'Status pagamento non valido');
        }
        
        return true;
    }

    /**
     * Valida dati partecipante prima delle transazioni
     * @param array $participant_data Dati del partecipante
     * @return bool|WP_Error true se valido, WP_Error se invalido
     */
    private function validate_participant_data($participant_data) {
        if (empty($participant_data['nome']) || empty($participant_data['cognome'])) {
            return new WP_Error('invalid_participant_name', 'Nome e cognome partecipante obbligatori');
        }
        
        if (!empty($participant_data['email']) && !filter_var($participant_data['email'], FILTER_VALIDATE_EMAIL)) {
            return new WP_Error('invalid_participant_email', 'Email partecipante non valida');
        }
        
        // Sanitizza nome completo
        $full_name = sanitize_text_field(trim($participant_data['nome'] . ' ' . $participant_data['cognome']));
        if (strlen($full_name) < 2 || strlen($full_name) > 255) {
            return new WP_Error('invalid_name_length', 'Nome partecipante deve essere tra 2 e 255 caratteri');
        }
        
        return true;
    }

    /**
     * Valida importo prima delle operazioni finanziarie
     * @param float $amount Importo da validare
     * @param float $max_amount Importo massimo consentito (opzionale)
     * @return bool|WP_Error true se valido, WP_Error se invalido
     */
    private function validate_amount($amount, $max_amount = null) {
        if (!is_numeric($amount) || $amount <= 0) {
            return new WP_Error('invalid_amount', 'Importo deve essere un numero positivo');
        }
        
        // Limiti ragionevoli per prevenire overflow
        if ($amount > 99999.99) {
            return new WP_Error('amount_too_large', 'Importo troppo elevato (max: €99,999.99)');
        }
        
        if ($max_amount !== null && $amount > $max_amount) {
            return new WP_Error('amount_exceeds_limit', "Importo supera il limite massimo di €$max_amount");
        }
        
        // Verifica precisione decimale
        if (round($amount, 2) !== (float)$amount) {
            return new WP_Error('invalid_decimal_precision', 'Importo deve avere massimo 2 decimali');
        }
        
        return true;
    }
}
