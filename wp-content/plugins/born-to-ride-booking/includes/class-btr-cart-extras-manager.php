<?php
/**
 * Gestione visibilità e modifica degli extra nel carrello WooCommerce
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.54
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Cart_Extras_Manager {
    
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
        // Hook per mostrare gli extra nel carrello
        add_filter('woocommerce_cart_item_name', [$this, 'display_extra_info_in_cart'], 15, 3);
        
        // Hook per aggiungere controlli di modifica
        add_action('woocommerce_cart_item_removed', [$this, 'handle_extra_removal'], 10, 2);
        add_action('woocommerce_after_cart_item_name', [$this, 'add_extra_controls'], 10, 2);
        
        // AJAX handlers per gestire le modifiche
        add_action('wp_ajax_btr_toggle_extra_in_cart', [$this, 'ajax_toggle_extra']);
        add_action('wp_ajax_nopriv_btr_toggle_extra_in_cart', [$this, 'ajax_toggle_extra']);
        
        // Aggiunge stili CSS per il carrello
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_styles']);
        
        // Hook per aggiornare i totali quando gli extra cambiano - priorità alta per forzare sync
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_totals_with_extras'], 999);
        
        // Aggiungi una sezione riepilogo extra nel carrello
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'display_extras_summary']);
    }
    
    /**
     * Mostra informazioni dettagliate sugli extra nel nome del prodotto
     */
    public function display_extra_info_in_cart($product_name, $cart_item, $cart_item_key) {
        // Se è un extra, mostra informazioni aggiuntive
        if (!empty($cart_item['from_extra'])) {
            $preventivo_id = $cart_item['preventivo_id'] ?? 0;
            
            if ($preventivo_id) {
                // Trova a quale partecipante appartiene questo extra
                $participant_info = $this->find_extra_participant($preventivo_id, $cart_item);
                
                if ($participant_info) {
                    $product_name .= '<div class="btr-extra-participant-info">';
                    $product_name .= '<small class="btr-extra-for">';
                    $product_name .= sprintf(
                        __('Per: %s', 'born-to-ride-booking'),
                        esc_html($participant_info['nome'])
                    );
                    $product_name .= '</small>';
                    $product_name .= '</div>';
                }
            }
        }
        
        return $product_name;
    }
    
    /**
     * Aggiunge controlli per modificare gli extra direttamente dal carrello
     */
    public function add_extra_controls($cart_item, $cart_item_key) {
        // Solo per prodotti del preventivo principale (non per gli extra stessi)
        if (empty($cart_item['preventivo_id']) || !empty($cart_item['from_extra'])) {
            return;
        }
        
        $preventivo_id = $cart_item['preventivo_id'];
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        
        if (empty($anagrafici) || !is_array($anagrafici)) {
            return;
        }
        
        // Mostra una sezione espandibile con gli extra
        ?>
        <div class="btr-cart-extras-section" data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>">
            <a href="#" class="btr-toggle-extras-view">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('Gestisci Extra', 'born-to-ride-booking'); ?>
                <span class="btr-extras-count"></span>
            </a>
            
            <div class="btr-extras-list" style="display: none;">
                <?php $this->render_extras_checkboxes($preventivo_id, $anagrafici); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizza i checkbox per gli extra
     */
    private function render_extras_checkboxes($preventivo_id, $anagrafici) {
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $costi_extra = get_post_meta($package_id, 'btr_costi_extra', true);
        
        if (empty($costi_extra)) {
            echo '<p>' . esc_html__('Nessun extra disponibile per questo pacchetto.', 'born-to-ride-booking') . '</p>';
            return;
        }
        
        // Ottieni gli extra attualmente nel carrello
        $current_extras = $this->get_extras_in_cart($preventivo_id);
        
        ?>
        <div class="btr-extras-grid">
            <?php foreach ($anagrafici as $index => $persona): 
                if (empty($persona['nome']) && empty($persona['cognome'])) continue;
                
                $nome_completo = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                ?>
                <div class="btr-participant-extras">
                    <h5><?php echo esc_html($nome_completo); ?></h5>
                    
                    <?php foreach ($costi_extra as $extra): 
                        $slug = sanitize_title($extra['slug'] ?? $extra['nome']);
                        $is_checked = $this->is_extra_selected($persona, $slug, $current_extras);
                        $importo = floatval($extra['importo'] ?? 0);
                        ?>
                        <label class="btr-extra-checkbox">
                            <input type="checkbox" 
                                   class="btr-extra-toggle"
                                   data-participant-index="<?php echo esc_attr($index); ?>"
                                   data-extra-slug="<?php echo esc_attr($slug); ?>"
                                   data-extra-name="<?php echo esc_attr($extra['nome']); ?>"
                                   data-extra-price="<?php echo esc_attr($importo); ?>"
                                   <?php checked($is_checked); ?>>
                            <?php echo esc_html($extra['nome']); ?>
                            <?php if ($importo != 0): ?>
                                <span class="btr-extra-price">
                                    <?php echo $importo > 0 ? '+' : ''; ?>€<?php echo number_format(abs($importo), 2); ?>
                                </span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="btr-extras-actions">
            <button type="button" class="button btr-update-extras" disabled>
                <?php esc_html_e('Aggiorna Extra', 'born-to-ride-booking'); ?>
            </button>
            <span class="btr-extras-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Aggiornamento in corso...', 'born-to-ride-booking'); ?>
            </span>
        </div>
        <?php
    }
    
    /**
     * Gestisce la rimozione di un extra dal carrello
     */
    public function handle_extra_removal($cart_item_key, $cart) {
        $cart_item = $cart->removed_cart_contents[$cart_item_key] ?? null;
        
        if (!$cart_item || empty($cart_item['from_extra'])) {
            return;
        }
        
        // Aggiorna i dati del preventivo se necessario
        $preventivo_id = $cart_item['preventivo_id'] ?? 0;
        if ($preventivo_id) {
            $this->update_preventivo_extra_data($preventivo_id);
        }
    }
    
    /**
     * AJAX handler per toggle degli extra
     */
    public function ajax_toggle_extra() {
        check_ajax_referer('btr-cart-extras', 'nonce');
        
        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $participant_index = intval($_POST['participant_index'] ?? 0);
        $extra_slug = sanitize_text_field($_POST['extra_slug'] ?? '');
        $extra_name = sanitize_text_field($_POST['extra_name'] ?? '');
        $extra_price = floatval($_POST['extra_price'] ?? 0);
        $checked = filter_var($_POST['checked'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (!$preventivo_id || !$extra_slug) {
            wp_send_json_error(['message' => __('Dati non validi.', 'born-to-ride-booking')]);
            return;
        }
        
        // Recupera i dati anagrafici
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!isset($anagrafici[$participant_index])) {
            wp_send_json_error(['message' => __('Partecipante non trovato.', 'born-to-ride-booking')]);
            return;
        }
        
        // Aggiorna i dati del partecipante
        if ($checked) {
            // Aggiungi l'extra
            if (!isset($anagrafici[$participant_index]['costi_extra_dettagliate'])) {
                $anagrafici[$participant_index]['costi_extra_dettagliate'] = [];
            }
            
            $anagrafici[$participant_index]['costi_extra_dettagliate'][$extra_slug] = [
                'nome' => $extra_name,
                'descrizione' => $extra_name,
                'importo' => $extra_price,
                'slug' => $extra_slug
            ];
            
            // Aggiorna anche il campo legacy se esistente
            if (!isset($anagrafici[$participant_index]['costi_extra'])) {
                $anagrafici[$participant_index]['costi_extra'] = [];
            }
            $anagrafici[$participant_index]['costi_extra'][$extra_slug] = 'on';
            
        } else {
            // Rimuovi l'extra
            unset($anagrafici[$participant_index]['costi_extra_dettagliate'][$extra_slug]);
            unset($anagrafici[$participant_index]['costi_extra'][$extra_slug]);
        }
        
        // Salva i dati aggiornati
        update_post_meta($preventivo_id, '_anagrafici_preventivo', $anagrafici);
        
        // Rigenera gli extra nel carrello
        $this->regenerate_cart_extras($preventivo_id);
        
        // Calcola il nuovo totale
        WC()->cart->calculate_totals();
        
        wp_send_json_success([
            'message' => __('Extra aggiornati con successo.', 'born-to-ride-booking'),
            'cart_total' => WC()->cart->get_cart_total(),
            'cart_hash' => WC()->cart->get_cart_hash()
        ]);
    }
    
    /**
     * Rigenera gli extra nel carrello basandosi sui dati del preventivo
     */
    private function regenerate_cart_extras($preventivo_id) {
        $cart = WC()->cart;
        
        // Rimuovi tutti gli extra esistenti per questo preventivo
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['from_extra']) && $cart_item['preventivo_id'] == $preventivo_id) {
                $cart->remove_cart_item($cart_item_key);
            }
        }
        
        // Forza la rigenerazione degli extra
        do_action('btr_regenerate_cart_extras', $preventivo_id);
        
        // Richiama il metodo ensure_extra_costs_cart_items di BTR_Checkout
        $checkout_instance = BTR_Checkout::instance();
        if (method_exists($checkout_instance, 'ensure_extra_costs_cart_items')) {
            $checkout_instance->ensure_extra_costs_cart_items($cart);
        }
    }
    
    /**
     * Aggiunge stili CSS per la gestione degli extra nel carrello
     */
    public function enqueue_cart_styles() {
        if (!is_cart()) {
            return;
        }
        
        wp_enqueue_style('dashicons');
        
        // Inline CSS
        $css = '
            .btr-cart-extras-section {
                margin-top: 10px;
                padding: 15px;
                background: #f7f7f7;
                border-radius: 5px;
            }
            
            .btr-toggle-extras-view {
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                color: #0073aa;
                font-weight: 500;
            }
            
            .btr-toggle-extras-view:hover {
                color: #005177;
            }
            
            .btr-extras-list {
                margin-top: 15px;
                padding: 15px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            
            .btr-extras-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .btr-participant-extras h5 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 14px;
                font-weight: 600;
            }
            
            .btr-extra-checkbox {
                display: block;
                padding: 5px 0;
                cursor: pointer;
            }
            
            .btr-extra-checkbox input {
                margin-right: 8px;
            }
            
            .btr-extra-price {
                color: #666;
                font-size: 0.9em;
                margin-left: 5px;
            }
            
            .btr-extras-actions {
                display: flex;
                align-items: center;
                gap: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
            
            .btr-extra-participant-info {
                margin-top: 5px;
            }
            
            .btr-extra-for {
                color: #666;
                font-style: italic;
            }
            
            /* Riepilogo extra */
            .btr-extras-summary {
                margin: 15px 0;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #0073aa;
            }
            
            .btr-extras-summary-title {
                font-weight: 600;
                margin-bottom: 10px;
            }
            
            .btr-extras-summary-list {
                font-size: 0.9em;
            }
            
            .btr-extras-summary-item {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
            }
            
            .btr-extras-loading .spinner {
                float: none;
                margin: 0 5px 0 0;
            }
        ';
        
        wp_add_inline_style('woocommerce-layout', $css);
        
        // JavaScript
        $js = '
        jQuery(function($) {
            // Toggle visualizzazione extra
            $(document).on("click", ".btr-toggle-extras-view", function(e) {
                e.preventDefault();
                $(this).siblings(".btr-extras-list").slideToggle();
                $(this).find(".dashicons").toggleClass("dashicons-admin-generic dashicons-arrow-down");
            });
            
            // Abilita/disabilita pulsante aggiorna
            $(document).on("change", ".btr-extra-toggle", function() {
                var section = $(this).closest(".btr-cart-extras-section");
                var hasChanges = false;
                
                section.find(".btr-extra-toggle").each(function() {
                    var originalState = $(this).prop("defaultChecked");
                    if ($(this).prop("checked") !== originalState) {
                        hasChanges = true;
                        return false;
                    }
                });
                
                section.find(".btr-update-extras").prop("disabled", !hasChanges);
            });
            
            // Gestisci aggiornamento extra
            $(document).on("click", ".btr-update-extras", function() {
                var button = $(this);
                var section = button.closest(".btr-cart-extras-section");
                var preventivo_id = section.data("preventivo-id");
                var loading = section.find(".btr-extras-loading");
                var changes = [];
                
                // Raccogli tutte le modifiche
                section.find(".btr-extra-toggle").each(function() {
                    var checkbox = $(this);
                    var originalState = checkbox.prop("defaultChecked");
                    var currentState = checkbox.prop("checked");
                    
                    if (currentState !== originalState) {
                        changes.push({
                            participant_index: checkbox.data("participant-index"),
                            extra_slug: checkbox.data("extra-slug"),
                            extra_name: checkbox.data("extra-name"),
                            extra_price: checkbox.data("extra-price"),
                            checked: currentState
                        });
                    }
                });
                
                if (changes.length === 0) return;
                
                // Mostra loading
                button.prop("disabled", true);
                loading.show();
                
                // Processa le modifiche una alla volta
                var processNextChange = function() {
                    if (changes.length === 0) {
                        // Tutte le modifiche completate
                        loading.hide();
                        location.reload(); // Ricarica la pagina per aggiornare il carrello
                        return;
                    }
                    
                    var change = changes.shift();
                    
                    $.post(btr_cart_ajax.ajax_url, {
                        action: "btr_toggle_extra_in_cart",
                        nonce: btr_cart_ajax.nonce,
                        preventivo_id: preventivo_id,
                        participant_index: change.participant_index,
                        extra_slug: change.extra_slug,
                        extra_name: change.extra_name,
                        extra_price: change.extra_price,
                        checked: change.checked
                    })
                    .done(function(response) {
                        if (response.success) {
                            processNextChange();
                        } else {
                            alert(response.data.message || "Errore durante l\'aggiornamento");
                            loading.hide();
                            button.prop("disabled", false);
                        }
                    })
                    .fail(function() {
                        alert("Errore di connessione");
                        loading.hide();
                        button.prop("disabled", false);
                    });
                };
                
                processNextChange();
            });
            
            // Conta gli extra selezionati
            function updateExtrasCount() {
                $(".btr-cart-extras-section").each(function() {
                    var section = $(this);
                    var count = section.find(".btr-extra-toggle:checked").length;
                    var countSpan = section.find(".btr-extras-count");
                    
                    if (count > 0) {
                        countSpan.text("(" + count + ")").show();
                    } else {
                        countSpan.hide();
                    }
                });
            }
            
            // Aggiorna conteggio all\'avvio
            updateExtrasCount();
            
            // Aggiorna conteggio quando cambiano i checkbox
            $(document).on("change", ".btr-extra-toggle", updateExtrasCount);
        });
        ';
        
        wp_add_inline_script('jquery', $js);
        
        // Localizza script per AJAX
        wp_localize_script('jquery', 'btr_cart_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr-cart-extras')
        ]);
    }
    
    /**
     * Mostra un riepilogo degli extra nel totale del carrello
     */
    public function display_extras_summary() {
        $extras_total = 0;
        $extras_list = [];
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['from_extra'])) {
                $price = $cart_item['custom_price'] ?? 0;
                $label = $cart_item['label_extra'] ?? __('Extra', 'born-to-ride-booking');
                
                $extras_total += $price * $cart_item['quantity'];
                
                if (!isset($extras_list[$label])) {
                    $extras_list[$label] = 0;
                }
                $extras_list[$label] += $price * $cart_item['quantity'];
            }
        }
        
        if ($extras_total != 0) {
            ?>
            <tr class="btr-extras-summary-row">
                <th><?php esc_html_e('Totale Extra', 'born-to-ride-booking'); ?></th>
                <td data-title="<?php esc_attr_e('Totale Extra', 'born-to-ride-booking'); ?>">
                    <span class="woocommerce-Price-amount amount">
                        <?php echo wc_price($extras_total); ?>
                    </span>
                    
                    <?php if (count($extras_list) > 0): ?>
                        <div class="btr-extras-summary-details" style="font-size: 0.85em; margin-top: 5px;">
                            <?php foreach ($extras_list as $label => $amount): ?>
                                <div style="color: #666;">
                                    <?php echo esc_html($label); ?>: 
                                    <?php echo wc_price($amount); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    
    /**
     * Trova il partecipante associato a un extra
     */
    private function find_extra_participant($preventivo_id, $cart_item) {
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        
        if (!is_array($anagrafici)) {
            return null;
        }
        
        $extra_slug = $cart_item['extra_slug'] ?? '';
        $extra_price = $cart_item['custom_price'] ?? 0;
        
        foreach ($anagrafici as $persona) {
            if (!empty($persona['costi_extra_dettagliate'])) {
                foreach ($persona['costi_extra_dettagliate'] as $slug => $extra) {
                    if ($slug === $extra_slug || 
                        (isset($extra['importo']) && floatval($extra['importo']) == $extra_price)) {
                        return [
                            'nome' => trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''))
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Ottieni gli extra attualmente nel carrello per un preventivo
     */
    private function get_extras_in_cart($preventivo_id) {
        $extras = [];
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['from_extra']) && $cart_item['preventivo_id'] == $preventivo_id) {
                $extras[] = $cart_item;
            }
        }
        
        return $extras;
    }
    
    /**
     * Verifica se un extra è selezionato
     */
    private function is_extra_selected($persona, $extra_slug, $current_extras) {
        // Verifica nei dati del partecipante
        if (!empty($persona['costi_extra_dettagliate'][$extra_slug])) {
            return true;
        }
        
        if (!empty($persona['costi_extra'][$extra_slug])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Aggiorna i totali del carrello quando cambiano gli extra
     */
    public function update_cart_totals_with_extras($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Aggiorna i prezzi personalizzati degli extra nel carrello
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['from_extra']) && isset($cart_item['custom_price'])) {
                $cart_item['data']->set_price($cart_item['custom_price']);
            }
        }
        
        // CORREZIONE CRITICA: Sincronizza i totali principali con il database del preventivo  
        static $sync_applied = false;
        
        if (!$sync_applied) {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                // Solo per gli item principali (non extra o assicurazioni)
                if (empty($cart_item['from_extra']) && empty($cart_item['from_assicurazione']) && 
                    !empty($cart_item['preventivo_id'])) {
                    
                    $preventivo_id = intval($cart_item['preventivo_id']);
                    
                    // CORREZIONE: Usa il totale finale dal summary (1.184,36€) come riferimento
                    $totale_corretto = 0;
                    $source = 'non_trovato';
                    
                    // Il checkout summary mostra 1.184,36€ come totale finale
                    // Recuperiamo i componenti che formano questo totale
                    $prezzi_base = floatval(get_post_meta($preventivo_id, '_package_price_no_extra', true) ?: 0);
                    $supplementi = floatval(get_post_meta($preventivo_id, '_supplemento_totale', true) ?: 0);
                    $notti_extra = floatval(get_post_meta($preventivo_id, '_extra_night_cost', true) ?: 0);
                    $costi_extra = floatval(get_post_meta($preventivo_id, '_costi_extra_totale', true) ?: 0);
                    $assicurazioni = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true) ?: 0);
                    
                    // Calcolo dal riepilogo dettagliato
                    $riepilogo = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
                    if (!empty($riepilogo)) {
                        $riepilogo_data = maybe_unserialize($riepilogo);
                        if (!empty($riepilogo_data['totali']['totale_generale'])) {
                            $totale_corretto = floatval($riepilogo_data['totali']['totale_generale']);
                            $source = 'riepilogo_dettagliato';
                        }
                    }
                    
                    // Se non trovato nel riepilogo, calcola dai componenti
                    if ($totale_corretto <= 0) {
                        // Dal summary: Prezzi base (548,55) + Supplementi (80) + Notti extra (115) + Suppl. extra (40) + Assicurazioni (410,81) + Costi extra (-10) = 1.184,36
                        $totale_corretto = $prezzi_base + $supplementi + $notti_extra + $costi_extra + $assicurazioni;
                        $source = 'calcolo_componenti';
                    }
                    
                    // Fallback ai meta tradizionali
                    if ($totale_corretto <= 0) {
                        $gran_totale_db = floatval(get_post_meta($preventivo_id, '_btr_grand_total', true));
                        if ($gran_totale_db > 0) {
                            $totale_corretto = $gran_totale_db;
                            $source = '_btr_grand_total';
                        } else {
                            $prezzo_totale = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
                            if ($prezzo_totale > 0) {
                                $totale_corretto = $prezzo_totale;
                                $source = '_prezzo_totale';
                            }
                        }
                    }
                    
                    if ($totale_corretto > 0) {
                        // FORZA il prezzo dell'item principale al totale corretto
                        $cart_item['data']->set_price($totale_corretto);
                        
                        // IMPORTANTE: Rimuovi extra e assicurazioni dal carrello per evitare doppio conteggio
                        $items_rimossi = 0;
                        foreach ($cart->get_cart() as $extra_key => $extra_item) {
                            if (($extra_item['from_extra'] ?? false) || ($extra_item['from_assicurazione'] ?? false)) {
                                if (($extra_item['preventivo_id'] ?? 0) == $preventivo_id) {
                                    WC()->cart->remove_cart_item($extra_key);
                                    $items_rimossi++;
                                }
                            }
                        }
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("BTR Cart - SYNC COMPLETO: €{$totale_corretto} (da {$source}), rimossi {$items_rimossi} item extra/assicurazioni");
                            error_log("BTR Cart - Componenti preventivo {$preventivo_id}:");
                            error_log("  - Prezzi base: €{$prezzi_base}");
                            error_log("  - Supplementi: €{$supplementi}");
                            error_log("  - Notti extra: €{$notti_extra}");
                            error_log("  - Costi extra: €{$costi_extra}");
                            error_log("  - Assicurazioni: €{$assicurazioni}");
                            error_log("  - TOTALE CALCOLATO: €{$totale_corretto}");
                        }
                        
                        break; // Sincronizza solo il primo item principale trovato
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("BTR Cart - ERRORE: Impossibile recuperare totale per preventivo {$preventivo_id}");
                        }
                    }
                }
            }
            $sync_applied = true;
        }
        
        // FORZA ricalcolo totali dopo la sincronizzazione
        if (method_exists($cart, 'calculate_totals')) {
            // Rimuovi temporaneamente questo hook per evitare loop
            remove_action('woocommerce_before_calculate_totals', [$this, 'update_cart_totals_with_extras'], 999);
            
            // Usa il metodo pubblico per invalidare i totali e forzare ricalcolo
            // Non possiamo usare reset_totals() perché è privato
            WC()->cart->set_session();
            WC()->cart->calculate_totals();
            
            // Rimetti il hook
            add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_totals_with_extras'], 999);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BTR Cart - Forzato ricalcolo totali WooCommerce dopo sincronizzazione');
            }
        }
    }
    
    /**
     * Aggiorna i dati del preventivo quando cambiano gli extra
     */
    private function update_preventivo_extra_data($preventivo_id) {
        // Ricalcola il totale del preventivo includendo gli extra
        $price_calculator = btr_price_calculator();
        
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
        
        $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici, $costi_extra_durata);
        $total_extra = $extra_costs_result['totale'];
        
        // Aggiorna il totale del preventivo
        $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_base', true));
        $prezzo_totale = $prezzo_base + $total_extra;
        
        update_post_meta($preventivo_id, '_prezzo_totale', $prezzo_totale);
        update_post_meta($preventivo_id, '_totale_costi_extra', $total_extra);
    }
}

// Inizializza la classe
add_action('init', function() {
    BTR_Cart_Extras_Manager::get_instance();
});