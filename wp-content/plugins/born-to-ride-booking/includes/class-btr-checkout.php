<?php
/**
 * Born To Ride – Checkout customization for WooCommerce Blocks
 * 
 * Nasconde il riepilogo standard dei blocchi React (Cart / Checkout)
 * e stampa un riepilogo personalizzato basato sui metadati del preventivo.
 * 
 * Il preventivo usato viene salvato in sessione dal metodo che converte
 * il preventivo stesso in carrello tramite:
 * 
 *   WC()->session->set( 'btr_preventivo_id', $preventivo_id );
 * 
 * Author: Born To Ride Booking
 * Version: 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BTR_Checkout' ) ) {
	class BTR_Checkout {
		/**
		 * Singleton instance.
		 *
		 * @var BTR_Checkout
		 */
		private static $instance = null;

		/**
		 * Return the singleton instance.
		 *
		 * @return BTR_Checkout
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Hook all the things.
		 */
		public function __construct() {
			// Nascondi il riepilogo standard dei Blocks.
			add_action( 'wp_enqueue_scripts', [ $this, 'hide_blocks_summary_css' ], 20 );
			
			// Enqueue foglio di stile custom (solo su checkout).
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
			
			// Stampa il riepilogo personalizzato (checkout classico).
			add_action( 'woocommerce_checkout_after_order_review', [ $this, 'print_custom_summary' ], 5 );
			
			// Stampa il riepilogo personalizzato anche nel checkout Blocks.
			add_action( 'woocommerce_before_checkout_form', [ $this, 'print_custom_summary_for_blocks' ], 5 );
			
			// Pulisci la sessione dopo l'ordine.
			add_action( 'woocommerce_thankyou', [ $this, 'cleanup_session' ] );
			
			// Pulisci la sessione quando il carrello viene svuotato
			add_action( 'woocommerce_cart_emptied', [ $this, 'cleanup_session' ] );
			add_action( 'woocommerce_before_cart_item_quantity_zero', [ $this, 'check_and_cleanup_session' ] );
			add_action( 'woocommerce_remove_cart_item', [ $this, 'check_and_cleanup_session' ] );
			
			// Regola i prezzi del carrello secondo le logiche pacchetto
			// Priorità alta (999) per assicurarsi che venga eseguito dopo altri hook
			add_action( 'woocommerce_before_calculate_totals', [ $this, 'adjust_cart_item_prices' ], 999, 1 );
			
			// Hook aggiuntivo per mantenere i prezzi dopo tutti i calcoli
			add_filter( 'woocommerce_cart_item_price', [ $this, 'maintain_cart_item_price' ], 999, 3 );
			add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'maintain_cart_item_subtotal' ], 999, 3 );
			
			// Hook per preservare i dati custom del carrello durante la sessione
			add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_cart_item_from_session' ], 999, 3 );
			
			// Forza il ricalcolo quando si arriva al checkout
			add_action( 'woocommerce_check_cart_items', [ $this, 'force_cart_recalculation' ], 5 );
			add_action( 'woocommerce_before_checkout_form', [ $this, 'force_cart_recalculation' ], 5 );
			
			// Registra il blocco con priorità alta per evitare conflitti
			add_action( 'init', [ $this, 'register_checkout_summary_block' ], 20 );
			
			// Inserisci automaticamente le assicurazioni selezionate nel carrello
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'ensure_insurance_cart_items' ], 9 );
			add_action( 'wp_loaded', [ $this, 'ensure_insurance_cart_items' ], 9 );
			
			// Inserisci automaticamente i costi extra selezionati nel carrello
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'ensure_extra_costs_cart_items' ], 10 );
			add_action( 'wp_loaded', [ $this, 'ensure_extra_costs_cart_items' ], 10 );
			
			// CORREZIONE 2025-01-20: Permetti prezzi negativi per sconti/riduzioni
			add_filter( 'woocommerce_product_get_price', [ $this, 'allow_negative_price' ], 999, 2 );
			add_filter( 'woocommerce_product_variation_get_price', [ $this, 'allow_negative_price' ], 999, 2 );
			add_filter( 'woocommerce_cart_item_price_html', [ $this, 'format_negative_price_html' ], 999, 2 );
			add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'format_negative_subtotal_html' ], 999, 3 );
			
			// CORREZIONE 2025-01-25: Supporto per WooCommerce Blocks (React checkout)
			// Assicura che i prezzi custom siano gestiti correttamente nell'API Store
			add_filter( 'woocommerce_store_api_product_price', [ $this, 'handle_store_api_product_price' ], 999, 3 );
			add_action( 'woocommerce_store_api_cart_update_cart_from_request', [ $this, 'ensure_custom_prices_in_store_api' ], 999, 2 );
		}

		/* ------------------------------------------------------------------- */
		/*  Front‑end
		/* ------------------------------------------------------------------- */

		/**
		 * Aggiunge un CSS inline che nasconde il riepilogo standard
		 * di Cart / Checkout Blocks.
		 */
		public function hide_blocks_summary_css() {
			if ( ! is_checkout() ) {
				return;
			}

			// wc-blocks-style è sempre presente quando i Blocks sono abilitati.
			$handle = wp_style_is( 'wc-blocks-style', 'enqueued' ) ? 'wc-blocks-style' : 'wp-block-library';
			$css = '
				/* Hide default Order‑Summary rendered by the Checkout Blocks */
				.wp-block-woocommerce-checkout-totals-block,
				.wp-block-woocommerce-checkout-order-summary-block{
					/*display:none !important;*/
				}
				.wp-block-woocommerce-checkout-order-summary-cart-items-block.wc-block-components-totals-wrapper {
				   display:none !important;
                }
			';
			wp_add_inline_style( $handle, $css );
		}

		/**
		 * Enqueue eventuali asset CSS/JS per il riepilogo custom.
		 */
		public function enqueue_assets() {
			if ( ! is_checkout() ) {
				return;
			}

			// Corretto percorso assets/css (non asstes/css)
			$style_url  = plugin_dir_url( __FILE__ ) . '../assets/css/btr-checkout.css';

			wp_enqueue_style(
				'btr-checkout',
				$style_url,
				[],
				BTR_VERSION
			);
			
			// Script per fix totale checkout - DISABILITATO
			/*
			$fix_script_url = plugin_dir_url( __FILE__ ) . '../assets/js/checkout-total-fix.js';
			wp_enqueue_script(
				'btr-checkout-total-fix',
				$fix_script_url,
				['jquery'],
				BTR_VERSION,
				true
			);
			*/
			
			// Script per l'integrazione con WooCommerce Blocks
			$integration_script_url = plugin_dir_url( __FILE__ ) . '../assets/js/btr-checkout-blocks-integration.js';
			
			// Dipendenze multiple per compatibilità con diverse versioni WooCommerce
			$block_dependencies = ['wc-blocks', 'wp-blocks', 'wp-element', 'wp-i18n'];
			
			// Aggiungi dipendenze specifiche WooCommerce se disponibili
			if (wp_script_is('wc-blocks-checkout', 'registered')) {
				$block_dependencies[] = 'wc-blocks-checkout';
			}
			if (wp_script_is('wc-blocks-data-store', 'registered')) {
				$block_dependencies[] = 'wc-blocks-data-store';
			}
			
			wp_enqueue_script(
				'btr-checkout-blocks-integration',
				$integration_script_url,
				$block_dependencies,
				BTR_VERSION,
				true
			);
			
			// Debug: Log delle dipendenze caricate
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[BTR] Dipendenze script integrazione: ' . implode(', ', $block_dependencies));
			}

			/* -----------------------------------------------------------------
			 * React bundle per il riepilogo custom
			 * ----------------------------------------------------------------- */
			$script_url  = plugin_dir_url( __FILE__ ) . '../assets/js/btr-checkout-summary.js';

			wp_enqueue_script(
				'btr-checkout-summary',
				$script_url,
				[
					'wp-element',      // React / ReactDOM wrapper
					'wp-i18n',
					'wp-components',
					'wp-hooks',
					'wc-blocks'        // assicura che i Blocks di WooCommerce siano caricati prima
				],
				BTR_VERSION,
				true
			);
		}

		/**
		 * FIX CRITICO: Recupera preventivo_id con multi-level fallback
		 */
		private function get_preventivo_id_with_recovery() {
			// Prova prima la chiave principale
			$preventivo_id = WC()->session->get( 'btr_preventivo_id' );
			
			// FALLBACK 1: Prova chiave alternativa
			if ( ! $preventivo_id ) {
				$preventivo_id = WC()->session->get( '_preventivo_id' );
				if ( $preventivo_id ) {
					error_log( 'RECOVERY: Preventivo ID recuperato da _preventivo_id: ' . $preventivo_id );
				}
			}
			
			// FALLBACK 2: Cerca in transient backup
			if ( ! $preventivo_id && WC()->session ) {
				$session_id = WC()->session->get_customer_id();
				if ( $session_id ) {
					$preventivo_id = get_transient( 'btr_preventivo_backup_' . $session_id );
					if ( $preventivo_id ) {
						error_log( 'RECOVERY: Preventivo ID recuperato da transient: ' . $preventivo_id );
						// Ripristina in sessione per uso futuro
						WC()->session->set( 'btr_preventivo_id', $preventivo_id );
					}
				}
			}
			
			// FALLBACK 3: Cerca negli item del carrello
			if ( ! $preventivo_id && WC()->cart && ! WC()->cart->is_empty() ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( isset( $cart_item['preventivo_id'] ) ) {
						$preventivo_id = intval( $cart_item['preventivo_id'] );
						error_log( 'RECOVERY: Preventivo ID recuperato da cart items: ' . $preventivo_id );
						// Ripristina in sessione per uso futuro
						WC()->session->set( 'btr_preventivo_id', $preventivo_id );
						break;
					}
				}
			}
			
			// VALIDATION: Verifica che il preventivo esista
			if ( $preventivo_id && ! get_post( $preventivo_id ) ) {
				error_log( 'ERROR: Preventivo ID ' . $preventivo_id . ' non esiste nel database!' );
				return 0;
			}
			
			return $preventivo_id;
		}

		/**
		 * Stampa il riepilogo custom nella pagina di checkout.
		 */
		public function print_custom_summary() {
			if ( ! is_checkout() ) {
				return;
			}

			$preventivo_id = $this->get_preventivo_id_with_recovery();
			if ( ! $preventivo_id ) {
				error_log( 'WARNING: Nessun preventivo trovato per il checkout' );
				return;
			}

			echo $this->get_summary_html( (int) $preventivo_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Rimuove il preventivo salvato in sessione una volta completato l'ordine.
		 */
		public function cleanup_session() {
			WC()->session->__unset( 'btr_preventivo_id' );
			WC()->session->__unset( 'btr_anagrafici_data' );
			WC()->session->__unset( '_preventivo_id' );
			if ( class_exists( 'BTR_Preventivo_To_Order' ) ) {
				BTR_Preventivo_To_Order::clear_detailed_cart_mode();
			}
		}
		
		/**
		 * Controlla se il carrello contiene ancora articoli del preventivo e pulisce se necessario
		 */
		public function check_and_cleanup_session() {
			// Verifica se il carrello ha ancora articoli del preventivo corrente
			$preventivo_id = WC()->session->get( 'btr_preventivo_id' );
			if ( ! $preventivo_id ) {
				return;
			}
			
			$has_preventivo_items = false;
			
			// Controlla se ci sono ancora articoli del preventivo nel carrello
			if ( WC()->cart && ! WC()->cart->is_empty() ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( isset( $cart_item['preventivo_id'] ) && $cart_item['preventivo_id'] == $preventivo_id ) {
						$has_preventivo_items = true;
						break;
					}
				}
			}
			
			// Se non ci sono più articoli del preventivo, pulisci la sessione
			if ( ! $has_preventivo_items ) {
				$this->cleanup_session();
			}
		}

		/**
		 * Stampa il riepilogo custom anche nel checkout Blocks (React).
		 */
		public function print_custom_summary_for_blocks() {
			if ( ! function_exists( 'wc_get_container' ) ) {
				return; // Non è il checkout blocks.
			}

			if ( ! is_checkout() ) {
				return;
			}

			$preventivo_id = WC()->session->get( 'btr_preventivo_id' );
			if ( ! $preventivo_id ) {
				return;
			}

			// Output di un container per il componente React del summary
			echo '<div id="btr-checkout-react-summary" style="margin-bottom:24px;"></div>';

			// Script per montare il componente React
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Assicurati che il bundle React contenente BTRCustomSummary sia stato caricato.
				if ( typeof BTRCustomSummary !== 'undefined' ) {
					BTRCustomSummary({
						containerId: 'btr-checkout-react-summary',
						summaryHtml: <?php echo json_encode( $this->get_summary_html( (int) $preventivo_id ) ); ?>
					});
				}
			});
			</script>
			<?php
		}

		/**
		 * Registra il blocco di riepilogo checkout
		 */
		public function register_checkout_summary_block() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}

			// Verifica se il blocco è già registrato
			$registry = WP_Block_Type_Registry::get_instance();
			if ( $registry->is_registered( 'btr/checkout-summary' ) ) {
				// Se già registrato, skip per evitare duplicati
				error_log( '[BTR] Blocco checkout summary già registrato, skip registrazione PHP' );
				return;
			}

			// Verifica che il file block.json esista
			$block_json_path = plugin_dir_path( __FILE__ ) . 'blocks/btr-checkout-summary/block.json';
			if ( ! file_exists( $block_json_path ) ) {
				error_log( '[BTR] ERRORE: block.json non trovato: ' . $block_json_path );
				return;
			}

			// Include il file con la funzione di render solo se esiste
			$block_php_path = plugin_dir_path( __FILE__ ) . 'blocks/btr-checkout-summary/block.php';
			if ( file_exists( $block_php_path ) ) {
				require_once $block_php_path;
			} else {
				error_log( '[BTR] ERRORE: block.php non trovato: ' . $block_php_path );
				return;
			}
			
			// Registra il blocco con controllo errori
			$registered = register_block_type(
				$block_json_path,
				[
					'render_callback' => 'btr_render_checkout_summary_block',
					'editor_script_handles' => [ 'btr-checkout-summary-editor' ],
					'style_handles' => [ 'btr-checkout-summary-style' ]
				]
			);
			
			if ( ! $registered ) {
				error_log( '[BTR] ERRORE: Impossibile registrare il blocco checkout summary' );
				return;
			} else {
				error_log( '[BTR] Blocco checkout summary registrato con successo' );
			}

			// Assicura che il blocco sia disponibile nell'editor
			add_filter( 'allowed_block_types_all', [ $this, 'allow_btr_checkout_summary_block' ], 10, 2 );
			
			// Hook specifico per WooCommerce Blocks inner block types
			add_filter( 'woocommerce_blocks_inner_blocks_allowed_block_types', [ $this, 'add_to_woocommerce_inner_blocks' ], 10, 2 );
			
			// Hook per modificare i blocchi consentiti nel checkout
			add_filter( 'render_block_context', [ $this, 'modify_checkout_block_context' ], 10, 2 );
		}
		
		/**
		 * Assicura che il blocco BTR sia consentito nell'editor
		 */
		public function allow_btr_checkout_summary_block( $allowed_blocks, $editor_context ) {
			// Se non ci sono restrizioni, mantieni così
			if ( ! is_array( $allowed_blocks ) ) {
				return $allowed_blocks;
			}
			
			// Aggiungi il nostro blocco se non è già presente
			if ( ! in_array( 'btr/checkout-summary', $allowed_blocks ) ) {
				$allowed_blocks[] = 'btr/checkout-summary';
			}
			
			return $allowed_blocks;
		}
		
		/**
		 * Aggiunge il blocco BTR ai tipi consentiti per i blocchi inner di WooCommerce
		 *
		 * @param array $allowed_blocks Array dei blocchi consentiti
		 * @param object $block_instance Istanza del blocco
		 * @return array Array modificato
		 */
		public function add_to_woocommerce_inner_blocks( $allowed_blocks, $block_instance = null ) {
			// Determina se siamo in un blocco WooCommerce checkout/cart
			$is_checkout_context = false;
			
			if ( $block_instance && isset( $block_instance->name ) ) {
				$wc_blocks = [
					'woocommerce/checkout',
					'woocommerce/checkout-totals-block',
					'woocommerce/checkout-order-summary-block',
					'woocommerce/cart-totals-block'
				];
				$is_checkout_context = in_array( $block_instance->name, $wc_blocks );
			}
			
			// Se siamo nel contesto giusto, aggiungi il nostro blocco
			if ( $is_checkout_context && ! in_array( 'btr/checkout-summary', $allowed_blocks ) ) {
				$allowed_blocks[] = 'btr/checkout-summary';
				error_log( '[BTR] Blocco aggiunto ai WooCommerce inner blocks per: ' . $block_instance->name );
			}
			
			return $allowed_blocks;
		}
		
		/**
		 * Modifica il contesto per il rendering dei blocchi checkout
		 *
		 * @param array $context Contesto attuale
		 * @param array $parsed_block Blocco parsato
		 * @return array Contesto modificato
		 */
		public function modify_checkout_block_context( $context, $parsed_block ) {
			// Se siamo in un blocco WooCommerce checkout, abilita il nostro blocco
			if ( isset( $parsed_block['blockName'] ) ) {
				$wc_checkout_blocks = [
					'woocommerce/checkout',
					'woocommerce/checkout-totals-block',
					'woocommerce/checkout-order-summary-block'
				];
				
				if ( in_array( $parsed_block['blockName'], $wc_checkout_blocks ) ) {
					$context['btr_checkout_enabled'] = true;
					error_log( '[BTR] Contesto checkout modificato per: ' . $parsed_block['blockName'] );
				}
			}
			
			return $context;
		}

		/**
		 * Carica gli asset del blocco
		 */
		private function enqueue_block_assets() {
			$dir = plugin_dir_path( __FILE__ ) . 'blocks/btr-checkout-summary/build';
			$url = plugin_dir_url( __FILE__ ) . 'blocks/btr-checkout-summary/build';

			// Verifica che la directory build esista
			if ( ! is_dir( $dir ) ) {
				error_log( '[BTR] ERRORE: Directory build del blocco non trovata: ' . $dir );
				return;
			}

			$asset_file_path = $dir . '/index.asset.php';
			if ( ! file_exists( $asset_file_path ) ) {
				error_log( '[BTR] ERRORE: Asset file del blocco non trovato: ' . $asset_file_path );
				return;
			}
			
			$asset_file = include $asset_file_path;
			
			// Verifica che l'asset file sia valido
			if ( ! is_array( $asset_file ) || ! isset( $asset_file['dependencies'] ) || ! isset( $asset_file['version'] ) ) {
				error_log( '[BTR] ERRORE: Asset file del blocco non valido' );
				return;
			}

			// Verifica che il file JS esista
			$js_path = $dir . '/index.js';
			if ( ! file_exists( $js_path ) ) {
				error_log( '[BTR] ERRORE: File JS del blocco non trovato: ' . $js_path );
				return;
			}

			wp_enqueue_script(
				'btr-checkout-summary',
				$url . '/index.js',
				$asset_file['dependencies'],
				$asset_file['version'],
				true
			);

			wp_enqueue_style(
				'btr-checkout-summary',
				$url . '/style-index.css',
				[ 'wc-blocks-style' ],
				$asset_file['version']
			);
		}

		/* ------------------------------------------------------------------- */
		/*  Helpers
		/* ------------------------------------------------------------------- */

		/**
		 * Costruisce l'HTML del riepilogo personalizzato.
		 *
		 * @param int $preventivo_id ID del post preventivo.
		 * @return string
		 */
		private function get_summary_html( $preventivo_id ) {
			if ( ! $preventivo_id || 'preventivo' !== get_post_type( $preventivo_id ) ) {
				return '';
			}

			// --- Meta principali ------------------------------------------------
			$nome_pacchetto   = get_post_meta( $preventivo_id, '_nome_pacchetto', true );
			$durata           = get_post_meta( $preventivo_id, '_durata', true );
			$date_range       = get_post_meta( $preventivo_id, '_date_ranges', true );
			
			// PRIORITA': Usa il breakdown dettagliato se disponibile
			$riepilogo_calcoli_dettagliato = get_post_meta( $preventivo_id, '_riepilogo_calcoli_dettagliato', true );
			
			if ( ! empty( $riepilogo_calcoli_dettagliato ) && is_array( $riepilogo_calcoli_dettagliato ) && 
			     ! empty( $riepilogo_calcoli_dettagliato['totali'] ) ) {
			     
				// Usa i totali dal breakdown dettagliato
				$totali = $riepilogo_calcoli_dettagliato['totali'];
				$prezzo_pacchetto = (float) $totali['subtotale_prezzi_base'];
				$supplemento_totale = (float) $totali['subtotale_supplementi_base'];
				$extra_night_total = (float) ( $totali['subtotale_notti_extra'] + $totali['subtotale_supplementi_extra'] );
				$grand_total_from_breakdown = (float) $totali['totale_generale'];
				
			} else {
				// FALLBACK: Usa meta semplici
				$prezzo_pacchetto = (float) get_post_meta( $preventivo_id, '_prezzo_totale', true );
				$supplemento_totale = (float) get_post_meta( $preventivo_id, '_supplemento_totale', true );
				$extra_night_total  = (float) get_post_meta( $preventivo_id, '_extra_night_total', true );
				$grand_total_from_breakdown = null;
			}

			// --- Calcola assicurazioni & extra con logica centralizzata --------
			$anagrafici = get_post_meta( $preventivo_id, '_anagrafici_preventivo', true );
			$costi_extra_durata = get_post_meta( $preventivo_id, '_costi_extra_durata', true );
			
			// CORREZIONE: Usa la stessa logica del template preventivo-review-fixed.php
			$price_calculator = btr_price_calculator();
			$extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici, $costi_extra_durata);
			
			// Estrai i totali calcolati
			$total_extra_costs_net = $extra_costs_result['totale']; // Include sia aggiunte che riduzioni
			$total_aggiunte = $extra_costs_result['totale_aggiunte'];
			$total_riduzioni = $extra_costs_result['totale_riduzioni'];
			
			// Calcola solo assicurazioni (separate dagli extra costs)
			$tot_assic = 0;
			if ( is_array( $anagrafici ) ) {
				foreach ( $anagrafici as $persona ) {
					if ( ! empty( $persona['assicurazioni_dettagliate'] ) ) {
						foreach ( $persona['assicurazioni_dettagliate'] as $ass ) {
							$tot_assic += isset( $ass['importo'] ) ? (float) $ass['importo'] : 0;
						}
					}
				}
			}

			// Totale finale: usa la stessa logica del preventivo-review-fixed.php
			if ( $grand_total_from_breakdown !== null ) {
				// Se abbiamo breakdown dettagliato, aggiungi solo assicurazioni e extra costs net
				$grand_total = $grand_total_from_breakdown + $tot_assic + $total_extra_costs_net;
			} else {
				// Fallback: calcola come nel template preventivo
				$prezzo_base = $prezzo_pacchetto + $supplemento_totale + $extra_night_total;
				$grand_total = $prezzo_base + $tot_assic + $total_extra_costs_net;
			}

			// --- Output ---------------------------------------------------------
			ob_start(); ?>
			<section class="btr-checkout-summary">
				<h3 class="btr-summary-title"><?php echo esc_html( $nome_pacchetto ); ?></h3>
				<ul class="btr-summary-list">
					<?php if ( $date_range ) : ?>
						<li><strong><?php esc_html_e( 'Data', 'born-to-ride-booking' ); ?>:</strong> <?php echo esc_html( $date_range ); ?></li>
					<?php endif; ?>
					<?php if ( $durata ) : ?>
						<li><strong><?php esc_html_e( 'Durata', 'born-to-ride-booking' ); ?>:</strong> <?php echo esc_html( $durata ); ?></li>
					<?php endif; ?>
					<li><strong><?php esc_html_e( 'Prezzo pacchetto', 'born-to-ride-booking' ); ?>:</strong> €<?php echo number_format_i18n( $prezzo_pacchetto, 2 ); ?></li>
					<?php if ( $supplemento_totale > 0 ) : ?>
						<li><strong><?php esc_html_e( 'Supplemento camera', 'born-to-ride-booking' ); ?>:</strong> €<?php echo number_format_i18n( $supplemento_totale, 2 ); ?></li>
					<?php endif; ?>
					<?php if ( $extra_night_total > 0 ) : ?>
						<li><strong><?php esc_html_e( 'Notte extra', 'born-to-ride-booking' ); ?>:</strong> €<?php echo number_format_i18n( $extra_night_total, 2 ); ?></li>
					<?php endif; ?>
					<?php if ( $tot_assic > 0 ) : ?>
						<li><strong><?php esc_html_e( 'Assicurazioni', 'born-to-ride-booking' ); ?>:</strong> €<?php echo number_format_i18n( $tot_assic, 2 ); ?></li>
					<?php endif; ?>
					<?php if ( $total_extra_costs_net != 0 ) : ?>
						<li><strong><?php esc_html_e( 'Costi extra', 'born-to-ride-booking' ); ?>:</strong> 
							<?php if ( $total_extra_costs_net >= 0 ) : ?>
								€<?php echo number_format_i18n( $total_extra_costs_net, 2 ); ?>
							<?php else : ?>
								-€<?php echo number_format_i18n( abs($total_extra_costs_net), 2 ); ?>
							<?php endif; ?>
						</li>
					<?php endif; ?>
				</ul>
				<p class="btr-summary-total">
					<strong><?php esc_html_e( 'Totale', 'born-to-ride-booking' ); ?>:</strong>
					€<?php echo number_format_i18n( $grand_total, 2 ); ?>
				</p>
			</section>
			<?php
			return ob_get_clean();
		}

		/**
		 * Forza il ricalcolo del carrello quando si arriva al checkout
		 */
		public function force_cart_recalculation() {
			if ( is_checkout() && WC()->cart && ! WC()->cart->is_empty() ) {
				// Triggera il ricalcolo dei totali
				WC()->cart->calculate_totals();
			}
		}
		
		/**
		 * Adjust each cart item price to include all preventivo components: base, supplements, extra nights, 
		 * insurances, and extra costs using centralized calculator.
		 * CORREZIONE: Allineato con logica preventivo-review-fixed.php
		 *
		 * @param WC_Cart $cart
		 */
		public function adjust_cart_item_prices( $cart ) {
		    // Usa un hash del carrello per determinare se dobbiamo ricalcolare
		    static $last_cart_hash = '';
		    $current_cart_hash = md5( serialize( $cart->get_cart() ) );
		    
		    // Log di debug per verificare che il metodo venga chiamato
		    if ( defined('WP_DEBUG') && WP_DEBUG ) {
		        error_log('BTR Checkout - adjust_cart_item_prices chiamato (hash: ' . substr($current_cart_hash, 0, 8) . ')');
		    }
		    
		    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		        return;
		    }
		    
		    // Se il carrello non è cambiato, non ricalcolare
		    if ( $last_cart_hash === $current_cart_hash ) {
		        if ( defined('WP_DEBUG') && WP_DEBUG ) {
		            error_log('BTR Checkout - Carrello non cambiato, skip ricalcolo');
		        }
		        return;
		    }
		    
		    // Aggiorna l'hash
		    $last_cart_hash = $current_cart_hash;
		    
		    // Evita loop infiniti durante il calcolo
		    remove_action( 'woocommerce_before_calculate_totals', [ $this, 'adjust_cart_item_prices' ], 999 );

		    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		        // Log dettagliato per ogni item
		        if ( defined('WP_DEBUG') && WP_DEBUG ) {
		            error_log('BTR Checkout - Processando item: ' . $cart_item['data']->get_name());
		            error_log('BTR Checkout - Dati item: ' . print_r([
		                'totale_camera' => $cart_item['totale_camera'] ?? 'non definito',
		                'prezzo_per_persona' => $cart_item['prezzo_per_persona'] ?? 'non definito',
		                'from_extra' => $cart_item['from_extra'] ?? false,
		                'from_assicurazione' => $cart_item['from_assicurazione'] ?? false,
		            ], true));
		        }
		        
		        // CORREZIONE 2025-01-25: Gestione unificata per custom_price (include assicurazioni e extra)
		        if ( isset( $cart_item['custom_price'] ) ) {
		            $custom_price = floatval($cart_item['custom_price']);
		            $cart_item['data']->set_price( $custom_price );
		            
		            if ( defined('WP_DEBUG') && WP_DEBUG ) {
		                $tipo = '';
		                if ( isset( $cart_item['from_assicurazione'] ) ) {
		                    $tipo = ' (Assicurazione)';
		                } elseif ( isset( $cart_item['from_extra'] ) ) {
		                    $tipo = ' (Extra)';
		                }
		                error_log('BTR Checkout - Usando custom_price' . $tipo . ': €' . $custom_price);
		            }
		            continue;
		        }

		        // Only adjust package items
		        if ( empty( $cart_item['prezzo_per_persona'] ) ) {
		            continue;
		        }

		        // CORREZIONE 2025-01-20: Non ricalcolare il prezzo qui
		        // Il prezzo corretto per camera è già stato calcolato in add_products_to_cart()
		        // e passato tramite custom_price o totale_camera
		        
		        // Se abbiamo un totale_camera, usa quello
		        if (isset($cart_item['totale_camera']) && $cart_item['totale_camera'] > 0) {
		            $cart_item['data']->set_price(floatval($cart_item['totale_camera']));
		            if (defined('WP_DEBUG') && WP_DEBUG) {
		                error_log('BTR Checkout - Usando totale_camera: €' . $cart_item['totale_camera']);
		            }
		        }
		        
		        // IMPORTANTE: Il codice seguente è stato commentato perché calcolava
		        // il prezzo TOTALE del preventivo invece del prezzo PER CAMERA
		        /*
		        $preventivo_id = isset($cart_item['preventivo_id']) ? intval($cart_item['preventivo_id']) : 0;
		        
		        if ($preventivo_id) {
		            // ERRORE: questo prende il prezzo TOTALE del preventivo, non per camera!
		            $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
		            
		            // ... resto del codice che calcola male ...
		        }
		        */
		    }
		    
		    // Riattiva l'hook dopo il calcolo
		    add_action( 'woocommerce_before_calculate_totals', [ $this, 'adjust_cart_item_prices' ], 999, 1 );
		}

		/**
		 * Mantieni il prezzo corretto del cart item (per visualizzazione)
		 *
		 * @param string $price_html
		 * @param array $cart_item
		 * @param string $cart_item_key
		 * @return string
		 */
		public function maintain_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		    // CORREZIONE 2025-01-20: Priorità a custom_price, poi totale_camera
		    if ( isset( $cart_item['custom_price'] ) && $cart_item['custom_price'] > 0 ) {
		        $price = floatval( $cart_item['custom_price'] );
		        return wc_price( $price );
		    } elseif ( isset( $cart_item['totale_camera'] ) && $cart_item['totale_camera'] > 0 ) {
		        $price = floatval( $cart_item['totale_camera'] );
		        return wc_price( $price );
		    }
		    
		    return $price_html;
		}

		/**
		 * Mantieni il subtotale corretto del cart item
		 *
		 * @param string $subtotal_html
		 * @param array $cart_item
		 * @param string $cart_item_key
		 * @return string
		 */
		public function maintain_cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		    // CORREZIONE 2025-01-20: Priorità a custom_price, poi totale_camera
		    if ( isset( $cart_item['custom_price'] ) && $cart_item['custom_price'] > 0 ) {
		        $price = floatval( $cart_item['custom_price'] );
		        $quantity = intval( $cart_item['quantity'] );
		        return wc_price( $price * $quantity );
		    } elseif ( isset( $cart_item['totale_camera'] ) && $cart_item['totale_camera'] > 0 ) {
		        $price = floatval( $cart_item['totale_camera'] );
		        $quantity = intval( $cart_item['quantity'] );
		        return wc_price( $price * $quantity );
		    }
		    
		    return $subtotal_html;
		}

		/**
		 * Ripristina i dati custom del cart item dalla sessione
		 *
		 * @param array $cart_item
		 * @param array $values
		 * @param string $key
		 * @return array
		 */
		public function restore_cart_item_from_session( $cart_item, $values, $key ) {
		    // Ripristina tutti i dati custom importanti
		    $custom_keys = [
		        'totale_camera', 'prezzo_per_persona', 'supplemento',
		        'price_child_f1', 'price_child_f2', 'price_child_f3', 'price_child_f4',
		        'assigned_child_f1', 'assigned_child_f2', 'assigned_child_f3', 'assigned_child_f4',
		        'number_of_persons', 'extra_night_pp', 'extra_night_total',
		        'preventivo_id', 'from_extra', 'from_assicurazione', 'custom_price'
		    ];
		    
		    foreach ( $custom_keys as $custom_key ) {
		        if ( isset( $values[$custom_key] ) ) {
		            $cart_item[$custom_key] = $values[$custom_key];
		        }
		    }
		    
		    // CORREZIONE 2025-01-20: Priorità a custom_price, poi totale_camera
		    if ( isset( $cart_item['custom_price'] ) && $cart_item['custom_price'] > 0 && isset( $cart_item['data'] ) ) {
		        $cart_item['data']->set_price( floatval( $cart_item['custom_price'] ) );
		    } elseif ( isset( $cart_item['totale_camera'] ) && $cart_item['totale_camera'] > 0 && isset( $cart_item['data'] ) ) {
		        $cart_item['data']->set_price( floatval( $cart_item['totale_camera'] ) );
		    }
		    
		    return $cart_item;
		}

		/**
		 * Assicura che ogni assicurazione selezionata nel preventivo
		 * venga rappresentata come voce di prodotto (con prezzo custom) nel carrello.
		 *
		 * @param WC_Cart|null $cart
		 */
		public function ensure_insurance_cart_items( $cart = null ) {
			if ( ! is_checkout() && ! is_cart() ) {
				return;
			}

			if ( is_null( $cart ) ) {
				$cart = WC()->cart;
			}

			if ( ! $cart || ! is_object( $cart ) ) {
				return;
			}

			// Se il carrello è stato popolato tramite flusso dettagliato si evitano doppie aggiunte
			if ( class_exists( 'BTR_Preventivo_To_Order' ) && BTR_Preventivo_To_Order::is_detailed_cart_mode( $cart ) ) {
				return;
			}

			$preventivo_id = WC()->session->get( 'btr_preventivo_id' );
			if ( ! $preventivo_id ) {
				return;
			}

			// Evita duplicazioni
			if ( did_action( 'btr_insurance_items_added' ) ) {
				return;
			}

			$anagrafici = get_post_meta( $preventivo_id, '_anagrafici_preventivo', true );
			if ( empty( $anagrafici ) || ! is_array( $anagrafici ) ) {
				return;
			}

			foreach ( $anagrafici as $persona ) {
				if ( empty( $persona['assicurazioni_dettagliate'] ) ) {
					continue;
				}

				foreach ( $persona['assicurazioni_dettagliate'] as $ass ) {
					$product_id   = isset( $ass['id'] ) ? intval( $ass['id'] ) : 0;
					$importo      = isset( $ass['importo'] ) ? floatval( $ass['importo'] ) : 0;
					$descrizione  = isset( $ass['descrizione'] ) ? sanitize_text_field( $ass['descrizione'] ) : '';

					if ( ! $product_id || $importo <= 0 ) {
						continue;
					}

					// Evita duplicazioni
					$already_in_cart = false;
					foreach ( $cart->get_cart() as $ci ) {
						if ( ! empty( $ci['from_assicurazione'] ) && intval( $ci['product_id'] ) === $product_id && $ci['custom_price'] == $importo ) {
							$already_in_cart = true;
							break;
						}
					}

					if ( $already_in_cart ) {
						continue;
					}

					$cart_data = [
						'label_assicurazione' => $descrizione,
						'custom_name'         => 'Assicurazione: ' . $descrizione,
						'custom_price'        => $importo,
						'from_assicurazione'  => 1,
						'preventivo_id'       => $preventivo_id,
					];

					$key = $cart->add_to_cart( $product_id, 1, 0, [], $cart_data );

					// Imposta immediatamente il prezzo
					if ( $key && isset( $cart->cart_contents[ $key ]['data'] ) ) {
						$cart->cart_contents[ $key ]['data']->set_price( $importo );
					}
				}
			}

			// Flag per evitare duplicazioni nella stessa request
			do_action( 'btr_insurance_items_added' );
		}

		/**
		 * Assicura che ogni costo extra selezionato nel preventivo
		 * venga rappresentato come voce di prodotto (con prezzo custom) nel carrello.
		 *
		 * @param WC_Cart|null $cart
		 */
		public function ensure_extra_costs_cart_items( $cart = null ) {
			if ( ! is_checkout() && ! is_cart() ) {
				return;
			}

			if ( is_null( $cart ) ) {
				$cart = WC()->cart;
			}

			if ( ! $cart || ! is_object( $cart ) ) {
				return;
			}

			// Evita di re-iniettare i costi extra se il carrello è già coerente con il riepilogo dettagliato
			if ( class_exists( 'BTR_Preventivo_To_Order' ) && BTR_Preventivo_To_Order::is_detailed_cart_mode( $cart ) ) {
				return;
			}

			$preventivo_id = WC()->session->get( 'btr_preventivo_id' );
			if ( ! $preventivo_id ) {
				return;
			}

			// Evita duplicazioni
			if ( did_action( 'btr_extra_costs_items_added' ) ) {
				return;
			}

			$anagrafici = get_post_meta( $preventivo_id, '_anagrafici_preventivo', true );
			if ( empty( $anagrafici ) || ! is_array( $anagrafici ) ) {
				return;
			}

			foreach ( $anagrafici as $persona ) {
				if ( empty( $persona['costi_extra_dettagliate'] ) ) {
					continue;
				}

				foreach ( $persona['costi_extra_dettagliate'] as $extra ) {
					$product_id   = isset( $extra['id'] ) ? intval( $extra['id'] ) : 0;
					$importo      = isset( $extra['importo'] ) ? floatval( $extra['importo'] ) : 0;
					$descrizione  = isset( $extra['descrizione'] ) ? sanitize_text_field( $extra['descrizione'] ) : '';

					// CORREZIONE 2025-01-20: Permetti valori negativi per sconti/riduzioni
					if ( $importo == 0 ) {
						continue;
					}
					
					// CORREZIONE 2025-01-20: Se non c'è product_id, usa un prodotto generico per extra costs
					if ( ! $product_id ) {
						// Cerca un prodotto generico per costi extra
						$generic_product = get_page_by_title( 'Costo Extra', OBJECT, 'product' );
						if ( ! $generic_product ) {
							// Crea un prodotto generico se non esiste
							$generic_product_id = wp_insert_post( [
								'post_title'   => 'Costo Extra',
								'post_type'    => 'product',
								'post_status'  => 'publish',
								'post_content' => 'Prodotto generico per costi extra del booking',
							] );
							
							if ( $generic_product_id && ! is_wp_error( $generic_product_id ) ) {
								// Imposta come prodotto semplice virtuale
								wp_set_object_terms( $generic_product_id, 'simple', 'product_type' );
								update_post_meta( $generic_product_id, '_virtual', 'yes' );
								update_post_meta( $generic_product_id, '_downloadable', 'no' );
								update_post_meta( $generic_product_id, '_stock_status', 'instock' );
								update_post_meta( $generic_product_id, '_price', '0' );
								update_post_meta( $generic_product_id, '_regular_price', '0' );
								
								$product_id = $generic_product_id;
							}
						} else {
							$product_id = $generic_product->ID;
						}
						
						// Se ancora non abbiamo un product_id, skip
						if ( ! $product_id ) {
							if ( defined('WP_DEBUG') && WP_DEBUG ) {
								error_log( 'BTR Checkout - Impossibile creare prodotto generico per costo extra: ' . $descrizione );
							}
							continue;
						}
					}

					// Evita duplicazioni
					$already_in_cart = false;
					foreach ( $cart->get_cart() as $ci ) {
						if ( ! empty( $ci['from_extra'] ) && intval( $ci['product_id'] ) === $product_id && $ci['custom_price'] == $importo ) {
							$already_in_cart = true;
							break;
						}
					}

					if ( $already_in_cart ) {
						continue;
					}

					$cart_data = [
						'label_extra'    => $descrizione,
						'custom_name'    => ($importo < 0 ? 'Sconto: ' : 'Extra: ') . $descrizione,
						'custom_price'   => $importo,
						'from_extra'     => 1,
						'preventivo_id'  => $preventivo_id,
					];

					$key = $cart->add_to_cart( $product_id, 1, 0, [], $cart_data );

					// Imposta immediatamente il prezzo
					if ( $key && isset( $cart->cart_contents[ $key ]['data'] ) ) {
						$cart->cart_contents[ $key ]['data']->set_price( $importo );
					}
				}
			}

			// Flag per evitare duplicazioni nella stessa request
			do_action( 'btr_extra_costs_items_added' );
		}
		
		/**
		 * CORREZIONE 2025-01-20: Permetti prezzi negativi per sconti/riduzioni
		 *
		 * @param mixed $price
		 * @param WC_Product $product
		 * @return mixed
		 */
		public function allow_negative_price( $price, $product ) {
			// Se il prezzo è già negativo, mantienilo così
			if ( $price < 0 ) {
				return $price;
			}
			
			// Per i prodotti nel carrello, controlla se hanno un custom_price negativo
			if ( WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $product->get_id() ) {
						if ( isset( $cart_item['custom_price'] ) && $cart_item['custom_price'] < 0 ) {
							return floatval( $cart_item['custom_price'] );
						}
					}
				}
			}
			
			return $price;
		}
		
		/**
		 * Formatta correttamente il prezzo HTML per valori negativi
		 *
		 * @param string $price_html
		 * @param array $cart_item
		 * @return string
		 */
		public function format_negative_price_html( $price_html, $cart_item ) {
			if ( isset( $cart_item['custom_price'] ) && $cart_item['custom_price'] < 0 ) {
				// Usa la funzione helper per formattare correttamente i prezzi negativi
				return btr_format_price_i18n( $cart_item['custom_price'] );
			}
			return $price_html;
		}
		
		/**
		 * Formatta correttamente il subtotale HTML per valori negativi
		 *
		 * @param string $subtotal_html
		 * @param array $cart_item
		 * @param string $cart_item_key
		 * @return string
		 */
		public function format_negative_subtotal_html( $subtotal_html, $cart_item, $cart_item_key ) {
			if ( isset( $cart_item['custom_price'] ) && $cart_item['custom_price'] < 0 ) {
				$quantity = intval( $cart_item['quantity'] );
				$subtotal = floatval( $cart_item['custom_price'] ) * $quantity;
				// Usa la funzione helper per formattare correttamente i prezzi negativi
				return btr_format_price_i18n( $subtotal );
			}
			return $subtotal_html;
		}
		
		/**
		 * CORREZIONE 2025-01-25: Gestisce i prezzi custom per WooCommerce Store API (Blocks)
		 * 
		 * @param float $price Il prezzo del prodotto
		 * @param WC_Product $product L'oggetto prodotto
		 * @param WP_REST_Request $request La richiesta REST
		 * @return float Il prezzo modificato
		 */
		public function handle_store_api_product_price( $price, $product, $request ) {
			// Se stiamo processando il carrello, controlla se il prodotto ha un prezzo custom
			if ( WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( $cart_item['data']->get_id() === $product->get_id() ) {
						if ( ! empty( $cart_item['custom_price'] ) ) {
							return floatval( $cart_item['custom_price'] );
						}
					}
				}
			}
			return $price;
		}
		
		/**
		 * CORREZIONE 2025-01-25: Assicura che i prezzi custom siano applicati nel contesto Store API
		 * 
		 * @param WC_Cart $cart L'oggetto carrello
		 * @param WP_REST_Request $request La richiesta REST
		 */
		public function ensure_custom_prices_in_store_api( $cart, $request ) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! empty( $cart_item['custom_price'] ) ) {
					$cart_item['data']->set_price( floatval( $cart_item['custom_price'] ) );
					
					// Log per debug
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'BTR Store API - Applicato custom price: €' . $cart_item['custom_price'] . ' per ' . $cart_item['data']->get_name() );
					}
				}
			}
		}
	}

	// Bootstrap.
	BTR_Checkout::instance();
}
