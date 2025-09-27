<?php
/**
 * Template per la pagina di riepilogo link pagamento
 * Segue le linee guida BTR-UI-STYLE-GUIDE.md
 *
 * @package BornToRideBooking
 * @since 1.0.254
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni i dati dalla sessione o dai parametri
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;
$payment_links = [];

// Prima prova a ottenere i link dalla sessione
if (function_exists('WC') && WC()->session) {
    $payment_links = WC()->session->get('btr_generated_payment_links', []);
    $session_preventivo_id = WC()->session->get('btr_payment_preventivo_id', 0);

    // Verifica che il preventivo corrisponda
    if ((int) $session_preventivo_id !== $preventivo_id) {
        $payment_links = [];
    }
}

// Se non ci sono link in sessione, prova a recuperarli dal database
if (empty($payment_links) && $preventivo_id && class_exists('BTR_Group_Payments')) {
    $group_payments = new BTR_Group_Payments();
    $stats = $group_payments->get_payment_stats($preventivo_id);

    // Se ci sono pagamenti esistenti, recuperali
    if ($stats && $stats['total_payments'] > 0) {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $table_links = $wpdb->prefix . 'btr_payment_links';

        $payment_links = $wpdb->get_results(
            $wpdb->prepare(
                "
                    SELECT
                        p.payment_id,
                        p.participant_name,
                        p.participant_email,
                        p.amount,
                        p.payment_status,
                        p.expires_at,
                        CONCAT(%s, l.link_hash) as payment_url
                    FROM {$table_payments} p
                    LEFT JOIN {$table_links} l ON p.payment_id = l.payment_id
                    WHERE p.preventivo_id = %d
                    AND l.is_active = 1
                    ORDER BY p.participant_name
                ",
                home_url('/pay-individual/'),
                $preventivo_id
            ),
            ARRAY_A
        );
    }
}

// Ottieni informazioni del preventivo
$preventivo = get_post($preventivo_id);
$nome_pacchetto = $preventivo ? $preventivo->post_title : '';
$nome_pacchetto_meta = get_post_meta($preventivo_id, '_nome_pacchetto', true);
if (!empty($nome_pacchetto_meta)) {
    $nome_pacchetto = $nome_pacchetto_meta;
}

// FIX v1.0.243: Usa _totale_preventivo che include assicurazioni invece di _prezzo_totale
$prezzo_totale = floatval(get_post_meta($preventivo_id, '_totale_preventivo', true));
if ($prezzo_totale <= 0) {
    // Fallback: calcola manualmente se _totale_preventivo non esiste
    $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
    $totale_assicurazioni = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true));
    $totale_costi_extra = floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true));
    $prezzo_totale = $prezzo_base + $totale_assicurazioni + $totale_costi_extra;
}

$date_range = get_post_meta($preventivo_id, '_date_ranges', true);
$partecipanti_totali = get_post_meta($preventivo_id, '_partecipanti_totali', true) ?: (is_array($payment_links) ? count($payment_links) : 0);

// Calcola statistiche
$total_links = is_array($payment_links) ? count($payment_links) : 0;
$paid_count = 0;
$pending_count = 0;
$total_paid_amount = 0;
$pending_names = [];

if (!empty($payment_links) && is_array($payment_links)) {
    foreach ($payment_links as $link) {
        $status = isset($link['payment_status']) ? $link['payment_status'] : '';

        if ($status === 'paid') {
            $paid_count++;
            $total_paid_amount += floatval($link['amount']);
        } else {
            $pending_count++;
            if (!empty($link['participant_name'])) {
                $pending_names[] = $link['participant_name'];
            }
        }
    }
}

$progress_percentage = $total_links > 0 ? round(($paid_count / $total_links) * 100) : 0;
$remaining_amount = max($prezzo_totale - $total_paid_amount, 0);
$all_paid = ($pending_count === 0 && $total_links > 0);
$pending_percentage = $total_links > 0 ? round(($pending_count / $total_links) * 100) : 0;

if (!function_exists('btr_generate_qr_data_uri')) {
    if (!class_exists('TCPDF2DBarcode')) {
        require_once BTR_PLUGIN_DIR . 'lib/tcpdf/tcpdf_barcodes_2d.php';
    }

    /**
     * Generate a QR code data URI for the given text using TCPDF.
     *
     * @param string $text The text encoded inside the QR.
     * @return string Data URI ready to be used inside an <img> tag.
     */
    function btr_generate_qr_data_uri($text) {
        if (empty($text) || !class_exists('TCPDF2DBarcode')) {
            return '';
        }

        static $cache = array();

        if (isset($cache[$text])) {
            return $cache[$text];
        }

        try {
            $barcode = new TCPDF2DBarcode($text, 'QRCODE,M');
        } catch (Exception $exception) {
            return '';
        }

        $png_data = $barcode->getBarcodePngData(6, 6, array(0, 0, 0));

        if (!empty($png_data)) {
            $cache[$text] = 'data:image/png;base64,' . base64_encode($png_data);
            return $cache[$text];
        }

        $svg_code = $barcode->getBarcodeSVGcode(3, 3, 'black');

        if (!empty($svg_code)) {
            $cache[$text] = 'data:image/svg+xml;base64,' . base64_encode($svg_code);
            return $cache[$text];
        }

        return '';
    }
}

if (!empty($payment_links) && is_array($payment_links)) {
    usort(
        $payment_links,
        static function ($a, $b) {
            $status_a = isset($a['payment_status']) && $a['payment_status'] === 'paid' ? 1 : 0;
            $status_b = isset($b['payment_status']) && $b['payment_status'] === 'paid' ? 1 : 0;

            if ($status_a === $status_b) {
                $name_a = isset($a['participant_name']) ? $a['participant_name'] : '';
                $name_b = isset($b['participant_name']) ? $b['participant_name'] : '';

                return strcasecmp((string) $name_a, (string) $name_b);
            }

            return $status_a < $status_b ? -1 : 1;
        }
    );
}

$pending_preview = array_slice($pending_names, 0, 3);
$extra_pending = max(count($pending_names) - count($pending_preview), 0);

// Calcola date viaggio
$data_inizio = '';
$data_fine = '';
$giorni_totali = 0;

if ($date_range && is_array($date_range)) {
    $first_range = reset($date_range);
    $last_range = end($date_range);

    if (isset($first_range['start']) && !empty($first_range['start'])) {
        $start_timestamp = strtotime($first_range['start']);
        if ($start_timestamp) {
            $data_inizio = wp_date('d M Y', $start_timestamp);
        }
    }

    if (isset($last_range['end']) && !empty($last_range['end'])) {
        $end_timestamp = strtotime($last_range['end']);
        if ($end_timestamp) {
            $data_fine = wp_date('d M Y', $end_timestamp);
        }

        if (!empty($start_timestamp ?? null) && !empty($end_timestamp ?? null)) {
            $giorni_totali = (int) ceil(($end_timestamp - $start_timestamp) / DAY_IN_SECONDS);
        }
    }
}

// Formattazioni comuni
$formatted_total = '€' . number_format_i18n($prezzo_totale, 2);
$formatted_paid = '€' . number_format_i18n($total_paid_amount, 2);
$formatted_remaining = '€' . number_format_i18n($remaining_amount, 2);

$primary_hint = $all_paid
    ? __('Tutti i partecipanti hanno già versato la loro quota.', 'born-to-ride-booking')
    : sprintf(
        _n(
            '%1$d partecipante deve ancora pagare (%2$s).',
            '%1$d partecipanti devono ancora pagare (%2$s).',
            $pending_count,
            'born-to-ride-booking'
        ),
        $pending_count,
        $formatted_remaining
    );

$secondary_hint = $all_paid
    ? __("Concludi l'ordine per finalizzare la prenotazione e aprire il checkout.", 'born-to-ride-booking')
    : __("Concludi l'ordine per bloccare la prenotazione e dai il via ai pagamenti del gruppo.", 'born-to-ride-booking');

$step_one_class = 'is-active';
$step_two_class = $paid_count > 0 ? 'is-active' : '';
$step_three_class = '';

if ($all_paid) {
    $step_one_class = 'is-complete';
    $step_two_class = 'is-complete';
    $step_three_class = 'is-active';
}

$stepper_title_id = 'btr-stepper-title-' . $preventivo_id;
?>
<div class="btr-payment-links-app btr-app">
    <div class="btr-container">
        <?php if (!$preventivo || empty($payment_links)) : ?>
            <section class="btr-card btr-empty-state">
                <div class="btr-card-body">
                    <div class="btr-empty-state__icon" aria-hidden="true">!</div>
                    <h2 class="btr-h3"><?php _e('Nessun link di pagamento trovato', 'born-to-ride-booking'); ?></h2>
                    <p class="btr-summary-text">
                        <?php _e('Non abbiamo trovato link attivi per questo preventivo. Verifica di aver generato i link correttamente oppure contatta il nostro supporto.', 'born-to-ride-booking'); ?>
                    </p>
                </div>
            </section>
        <?php else : ?>
            <section class="btr-card btr-card--summary" aria-labelledby="btr-group-heading">
                <div class="btr-card-body">
                    <span class="btr-summary-tag"><?php _e('Prenotazione di gruppo', 'born-to-ride-booking'); ?></span>
                    <h1 id="btr-group-heading" class="btr-summary-title"><?php echo esc_html($nome_pacchetto); ?></h1>
                    <?php if ($data_inizio && $data_fine) : ?>
                        <p class="btr-summary-text btr-summary-text--dates">
                            <?php echo esc_html($data_inizio . ' → ' . $data_fine); ?>
                            <?php if ($giorni_totali > 0) : ?>
                                <span class="btr-summary-text-separator">•</span>
                                <?php
                                printf(
                                    /* translators: %d = number of days */
                                    esc_html__('%d giorni di viaggio', 'born-to-ride-booking'),
                                    absint($giorni_totali)
                                );
                                ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <p class="btr-summary-text">
                        <?php
                        printf(
                            /* translators: 1: total participants, 2: payers */
                            esc_html__('%1$d posti confermati · %2$d link attivi', 'born-to-ride-booking'),
                            intval($partecipanti_totali),
                            intval($total_links)
                        );
                        ?>
                    </p>
                    <div class="btr-hero-callout">
                        <div class="btr-hero-callout__copy">
                            <p class="btr-summary-text">
                                <strong><?php echo esc_html($primary_hint); ?></strong>
                            </p>
                            <p class="btr-hero-callout__hint"><?php echo esc_html($secondary_hint); ?></p>
                            <?php if (!$all_paid && !empty($pending_preview)) : ?>
                                <p class="btr-hero-callout__hint">
                                    <?php _e('In attesa:', 'born-to-ride-booking'); ?>
                                    <?php echo esc_html(implode(', ', $pending_preview)); ?>
                                    <?php if ($extra_pending > 0) : ?>
                                        <?php
                                        printf(
                                            /* translators: %d = number of additional pending participants */
                                            esc_html__('e altri %d', 'born-to-ride-booking'),
                                            intval($extra_pending)
                                        );
                                        ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <div class="btr-inline-actions btr-hero-callout__actions">
                                <button
                                    type="button"
                                    class="btr-btn btr-btn-primary btr-btn-lg js-organizer-proceed"
                                    data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>"
                                >
                                    <?php _e('Concludi ordine e vai al checkout', 'born-to-ride-booking'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="btr-btn btr-btn-secondary btr-btn-lg"
                                    id="send-all-emails"
                                >
                                    <?php _e('Invia tutti i link via email', 'born-to-ride-booking'); ?>
                                </button>
                            </div>
                            <div class="btr-summary-highlights">
                                <dl class="btr-summary-highlights__item">
                                    <dt><?php _e('Totale prenotazione', 'born-to-ride-booking'); ?></dt>
                                    <dd><?php echo esc_html($formatted_total); ?></dd>
                                </dl>
                                <dl class="btr-summary-highlights__item">
                                    <dt><?php _e('Pagato finora', 'born-to-ride-booking'); ?></dt>
                                    <dd class="text-success"><?php echo esc_html($formatted_paid); ?></dd>
                                </dl>
                                <dl class="btr-summary-highlights__item">
                                    <dt><?php _e('Importo residuo', 'born-to-ride-booking'); ?></dt>
                                    <dd class="<?php echo $all_paid ? 'text-success' : 'text-warning'; ?>"><?php echo esc_html($formatted_remaining); ?></dd>
                                </dl>
                            </div>
                            <p class="btr-hero-callout__hint btr-hero-callout__hint--muted">
                                <?php _e("Il checkout si apre in una nuova scheda: nessun importo viene addebitato all'organizzatore e i link restano attivi per il gruppo.", 'born-to-ride-booking'); ?>
                            </p>
                        </div>
                        <div class="btr-hero-progress" aria-label="<?php esc_attr_e('Avanzamento dei pagamenti di gruppo', 'born-to-ride-booking'); ?>">
                            <div class="btr-progress-text">
                                <span><?php echo esc_html($progress_percentage); ?>%</span>
                                <span><?php echo esc_html($paid_count . '/' . $total_links); ?> <?php _e('pagamenti', 'born-to-ride-booking'); ?></span>
                            </div>
                            <div class="btr-progress-track">
                                <span style="width: <?php echo esc_attr($progress_percentage); ?>%;"></span>
                            </div>
                            <small class="btr-progress-meta">
                                <?php
                                printf(
                                    /* translators: 1: collected amount, 2: total amount */
                                    esc_html__('%1$s raccolti su %2$s', 'born-to-ride-booking'),
                                    esc_html($formatted_paid),
                                    esc_html($formatted_total)
                                );
                                ?>
                            </small>
                            <?php if (!$all_paid) : ?>
                                <small class="btr-progress-meta btr-progress-meta--warning">
                                    <?php
                                    printf(
                                        /* translators: %d = pending percentage */
                                        esc_html__('%d%% ancora in attesa', 'born-to-ride-booking'),
                                        intval($pending_percentage)
                                    );
                                    ?>
                                </small>
                            <?php endif; ?>
                            <div class="btr-stepper-block" aria-labelledby="<?php echo esc_attr($stepper_title_id); ?>">
                                <span id="<?php echo esc_attr($stepper_title_id); ?>" class="btr-stepper-title"><?php _e('Prossimi passaggi', 'born-to-ride-booking'); ?></span>
                                <ul class="btr-stepper btr-stepper--compact">
                                    <li class="btr-stepper__item <?php echo esc_attr($step_one_class); ?>">
                                        <span class="btr-stepper__number">1</span>
                                        <div class="btr-stepper__content">
                                            <span class="btr-stepper__label"><?php _e("Concludi l'ordine", 'born-to-ride-booking'); ?></span>
                                            <small><?php _e('Apri il checkout per confermare la prenotazione', 'born-to-ride-booking'); ?></small>
                                        </div>
                                    </li>
                                    <li class="btr-stepper__item <?php echo esc_attr($step_two_class); ?>">
                                        <span class="btr-stepper__number">2</span>
                                        <div class="btr-stepper__content">
                                            <span class="btr-stepper__label"><?php _e('Condividi i link', 'born-to-ride-booking'); ?></span>
                                            <small>
                                                <?php
                                                printf(
                                                    /* translators: %d = total links */
                                                    esc_html__("%d link personalizzati pronti all'invio", 'born-to-ride-booking'),
                                                    intval($total_links)
                                                );
                                                ?>
                                            </small>
                                        </div>
                                    </li>
                                    <li class="btr-stepper__item <?php echo esc_attr($step_three_class); ?>">
                                        <span class="btr-stepper__number">3</span>
                                        <div class="btr-stepper__content">
                                            <span class="btr-stepper__label"><?php _e('Monitora i pagamenti', 'born-to-ride-booking'); ?></span>
                                            <small>
                                                <?php
                                                printf(
                                                    /* translators: %d = paid count */
                                                    esc_html__('%d pagamenti ricevuti finora', 'born-to-ride-booking'),
                                                    intval($paid_count)
                                                );
                                                ?>
                                            </small>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="btr-card" aria-labelledby="btr-payment-links-heading">
                <div class="btr-card-body">
                    <div class="btr-section-heading">
                        <div>
                            <span class="btr-summary-tag"><?php _e('Step 2', 'born-to-ride-booking'); ?></span>
                            <h2 id="btr-payment-links-heading" class="btr-card-title"><?php _e('Link di pagamento per il gruppo', 'born-to-ride-booking'); ?></h2>
                            <p class="btr-summary-text">
                                <?php _e('Monitora lo stato di ciascun partecipante e condividi il link corretto con un clic.', 'born-to-ride-booking'); ?>
                            </p>
                        </div>
                        <div class="btr-inline-actions btr-section-heading__actions">
                            <button type="button" class="btr-btn btr-btn-secondary btr-btn-sm btr-filter-tab active" data-filter="all">
                                <?php
                                printf(
                                    /* translators: %d = total links */
                                    esc_html__('Tutti (%d)', 'born-to-ride-booking'),
                                    intval($total_links)
                                );
                                ?>
                            </button>
                            <button type="button" class="btr-btn btr-btn-secondary btr-btn-sm btr-filter-tab" data-filter="paid">
                                <?php
                                printf(
                                    /* translators: %d = paid count */
                                    esc_html__('Pagati (%d)', 'born-to-ride-booking'),
                                    intval($paid_count)
                                );
                                ?>
                            </button>
                            <button type="button" class="btr-btn btr-btn-secondary btr-btn-sm btr-filter-tab" data-filter="pending">
                                <?php
                                printf(
                                    /* translators: %d = pending count */
                                    esc_html__('In attesa (%d)', 'born-to-ride-booking'),
                                    intval($pending_count)
                                );
                                ?>
                            </button>
                        </div>
                    </div>
                    <div class="btr-link-grid">
                        <?php foreach ($payment_links as $link) : ?>
                            <?php
                            $is_paid = isset($link['payment_status']) && $link['payment_status'] === 'paid';
                            $amount_formatted = '€' . number_format_i18n(floatval($link['amount']), 2);
                            $payment_url = isset($link['payment_url']) ? $link['payment_url'] : '';
                            $qr_data_uri = $payment_url ? btr_generate_qr_data_uri($payment_url) : '';
                            $expires_at = '';
                            if (!empty($link['expires_at'])) {
                                $expires_timestamp = strtotime($link['expires_at']);
                                if ($expires_timestamp) {
                                    $expires_at = wp_date(get_option('date_format'), $expires_timestamp);
                                }
                            }

                            if ('' === $link_display) {
                                $link_display = __('Apri pagina di pagamento', 'born-to-ride-booking');
                            }

                            $amount_note = $is_paid
                                ? __('Quota saldata', 'born-to-ride-booking')
                                : __('Da versare', 'born-to-ride-booking');

                            $link_display = '';
                            if (!empty($payment_url)) {
                                $parsed_url = wp_parse_url($payment_url);
                                $link_display_parts = '';
                                if (!empty($parsed_url['host'])) {
                                    $link_display_parts = $parsed_url['host'];
                                }
                                if (!empty($parsed_url['path'])) {
                                    $link_display_parts .= $parsed_url['path'];
                                }
                                if (!empty($parsed_url['query'])) {
                                    $link_display_parts .= '?' . $parsed_url['query'];
                                }
                                $link_display = $link_display_parts ? $link_display_parts : $payment_url;

                                $length_function = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
                                $substr_function = function_exists('mb_substr') ? 'mb_substr' : 'substr';

                                if ($length_function($link_display) > 60) {
                                    $link_display = $substr_function($link_display, 0, 57) . '…';
                                }
                            }

                            $participant_safe_name = isset($link['participant_name']) ? wp_strip_all_tags($link['participant_name']) : '';
                            $qr_button_label = $participant_safe_name
                                ? sprintf(__('Mostra QR code per %s', 'born-to-ride-booking'), $participant_safe_name)
                                : __('Mostra QR code', 'born-to-ride-booking');
                            ?>
                            <div class="btr-link-card btr-payment-card <?php echo $is_paid ? 'paid' : 'pending'; ?>" data-status="<?php echo $is_paid ? 'paid' : 'pending'; ?>">
                                <div class="btr-link-card__header">
                                    <div class="btr-link-card__identity">
                                        <span class="btr-link-card__name"><?php echo esc_html($link['participant_name']); ?></span>
                                        <span class="btr-link-card__email"><?php echo esc_html($link['participant_email']); ?></span>
                                    </div>
                                </div>
                                <div class="btr-link-card__body">
                                    <div class="btr-link-card__summary">
                                        <div class="btr-link-card__amount">
                                            <span class="btr-link-card__amount-value"><?php echo esc_html($amount_formatted); ?></span>
                                            <span class="btr-link-card__amount-note"><?php echo esc_html($amount_note); ?></span>
                                        </div>
                                        <span class="btr-status-badge <?php echo $is_paid ? 'is-paid' : 'is-pending'; ?>">
                                            <?php echo $is_paid ? esc_html__('Pagato', 'born-to-ride-booking') : esc_html__('In attesa', 'born-to-ride-booking'); ?>
                                        </span>
                                    </div>
                                    <dl class="btr-link-card__list">
                                        <?php if ($expires_at && !$is_paid) : ?>
                                            <dt><?php _e('Scadenza link', 'born-to-ride-booking'); ?></dt>
                                            <dd><?php echo esc_html($expires_at); ?></dd>
                                        <?php endif; ?>
                                        <dt><?php _e('Link diretto', 'born-to-ride-booking'); ?></dt>
                                        <dd>
                                            <a
                                                class="btr-link-card__link"
                                                href="<?php echo esc_url($payment_url); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <?php echo esc_html($link_display); ?>
                                            </a>
                                        </dd>
                                    </dl>
                                    <?php if (!$is_paid) : ?>
                                        <p class="btr-link-card__hint">
                                            <?php _e('Invia il link o mostra il QR code per incassare la quota.', 'born-to-ride-booking'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="btr-link-card__actions">
                                    <div class="btr-link-card__actions-group">
                                        <a
                                            class="btr-btn btr-btn-secondary btr-btn-sm btn-open-link"
                                            href="<?php echo esc_url($payment_url); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <?php _e('Apri link', 'born-to-ride-booking'); ?>
                                        </a>
                                        <button
                                            type="button"
                                            class="btr-btn btr-btn-secondary btr-btn-sm btn-copy-link"
                                            data-link="<?php echo esc_attr($payment_url); ?>"
                                        >
                                            <?php _e('Copia link', 'born-to-ride-booking'); ?>
                                        </button>
                                    </div>
                                    <?php if (!$is_paid) : ?>
                                        <button
                                            type="button"
                                            class="btr-btn btr-btn-primary btr-btn-sm btn-send-email"
                                            data-payment-id="<?php echo esc_attr($link['payment_id']); ?>"
                                        >
                                            <?php _e('Invia email', 'born-to-ride-booking'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="btr-btn btr-btn-secondary btr-btn-sm btn-show-qr"
                                        data-link="<?php echo esc_attr($payment_url); ?>"
                                        data-name="<?php echo esc_attr($participant_safe_name); ?>"
                                        data-qr="<?php echo esc_attr($qr_data_uri); ?>"
                                        aria-label="<?php echo esc_attr($qr_button_label); ?>"
                                    >
                                        <?php _e('QR code', 'born-to-ride-booking'); ?>
                                    </button>
                                    <?php if ($is_paid) : ?>
                                        <span class="btr-status-pill"><?php _e('Pagamento completato', 'born-to-ride-booking'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="btr-footer-actions" aria-labelledby="btr-footer-actions-heading">
                <h2 id="btr-footer-actions-heading" class="btr-card-title"><?php _e('Altre azioni utili', 'born-to-ride-booking'); ?></h2>
                <div class="btr-footer-actions__inner">
                    <button type="button" class="btr-btn btr-btn-secondary" onclick="window.print();">
                        <?php _e('Stampa riepilogo', 'born-to-ride-booking'); ?>
                    </button>
                    <button
                        type="button"
                        class="btr-btn btr-btn-primary js-organizer-proceed"
                        data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>"
                    >
                        <?php _e('Concludi ordine e apri il checkout', 'born-to-ride-booking'); ?>
                    </button>
                    <?php if (current_user_can('edit_posts')) : ?>
                        <a
                            class="btr-btn btr-btn-secondary"
                            href="<?php echo esc_url(admin_url('edit.php?post_type=btr_preventivi&page=btr-group-payments&preventivo_id=' . $preventivo_id)); ?>"
                        >
                            <?php _e('Gestione da backend', 'born-to-ride-booking'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <p class="btr-footer-note">
                    <?php _e('Se hai bisogno di modificare i partecipanti o rigenerare i link, puoi farlo in qualsiasi momento dalla dashboard di amministrazione.', 'born-to-ride-booking'); ?>
                </p>
            </section>
        <?php endif; ?>
    </div>
</div>

<div id="btr-toast" class="btr-toast" aria-live="polite"></div>

<div id="btr-confirm-modal" class="btr-modal" role="dialog" aria-modal="true" aria-labelledby="btr-confirm-title" aria-hidden="true">
    <div class="btr-modal-content" role="document">
        <button type="button" class="btr-modal-close js-confirm-close" aria-label="<?php esc_attr_e('Chiudi conferma', 'born-to-ride-booking'); ?>">&times;</button>
        <h3 id="btr-confirm-title" class="btr-modal-title"><?php _e('Conferma azione', 'born-to-ride-booking'); ?></h3>
        <p id="btr-confirm-message" class="btr-modal-message"><?php _e('Sei sicuro di voler procedere?', 'born-to-ride-booking'); ?></p>
        <div class="btr-inline-actions btr-modal-actions">
            <button type="button" class="btr-btn btr-btn-secondary js-confirm-cancel"><?php _e('Annulla', 'born-to-ride-booking'); ?></button>
            <button type="button" class="btr-btn btr-btn-primary js-confirm-accept"><?php _e('Conferma', 'born-to-ride-booking'); ?></button>
        </div>
    </div>
</div>

<div id="btr-qr-modal" class="btr-modal" role="dialog" aria-modal="true" aria-labelledby="btr-qr-title" aria-hidden="true">
    <div class="btr-modal-content btr-modal-content--qr" role="document">
        <button type="button" class="btr-modal-close js-qr-close" aria-label="<?php esc_attr_e('Chiudi QR code', 'born-to-ride-booking'); ?>">&times;</button>
        <h3 id="btr-qr-title" class="btr-modal-title"><?php _e('QR code di pagamento', 'born-to-ride-booking'); ?></h3>
        <p id="btr-qr-subtitle" class="btr-modal-message"></p>
        <div id="btr-qr-container" class="btr-qr-container" role="img" aria-live="polite"></div>
        <p id="btr-qr-link" class="btr-qr-link">
            <span class="btr-qr-link-label"><?php _e('Link diretto:', 'born-to-ride-booking'); ?></span>
            <a id="btr-qr-link-anchor" href="#" target="_blank" rel="noopener noreferrer"></a>
        </p>
    </div>
</div>

<script>
jQuery(function($) {
    const $toast = $('#btr-toast');

    const $confirmModal = $('#btr-confirm-modal');
    const $confirmTitle = $('#btr-confirm-title');
    const $confirmMessage = $('#btr-confirm-message');
    const $confirmAccept = $confirmModal.find('.js-confirm-accept');
    const $confirmCancel = $confirmModal.find('.js-confirm-cancel');
    const $confirmClose = $confirmModal.find('.js-confirm-close');
    let confirmCallback = null;

    const $qrModal = $('#btr-qr-modal');
    const $qrContainer = $('#btr-qr-container');
    const $qrSubtitle = $('#btr-qr-subtitle');
    const $qrLinkWrapper = $('#btr-qr-link');
    const $qrLinkAnchor = $('#btr-qr-link-anchor');
    const $qrClose = $qrModal.find('.js-qr-close');

    function showToast(message, type = 'success') {
        const safeMessage = (message === null || message === undefined) ? '' : String(message).trim();

        if (!safeMessage.length) {
            return;
        }

        const toastType = type === 'error' ? 'error' : 'success';
        $toast.removeClass('success error').addClass(toastType).text(safeMessage).addClass('show');

        setTimeout(function() {
            $toast.removeClass('show');
        }, 3200);
    }

    function closeConfirmModal() {
        $confirmModal.removeClass('is-visible').attr('aria-hidden', 'true');
        confirmCallback = null;
    }

    function openConfirmModal(options) {
        const settings = $.extend({
            title: '<?php echo esc_js(__('Conferma azione', 'born-to-ride-booking')); ?>',
            message: '<?php echo esc_js(__('Sei sicuro di voler procedere?', 'born-to-ride-booking')); ?>',
            confirmLabel: '<?php echo esc_js(__('Conferma', 'born-to-ride-booking')); ?>',
            cancelLabel: '<?php echo esc_js(__('Annulla', 'born-to-ride-booking')); ?>',
            onConfirm: null
        }, options || {});

        confirmCallback = typeof settings.onConfirm === 'function' ? settings.onConfirm : null;

        $confirmTitle.text(settings.title);
        $confirmMessage.text(settings.message);
        $confirmAccept.text(settings.confirmLabel);
        $confirmCancel.text(settings.cancelLabel);

        $confirmModal.addClass('is-visible').attr('aria-hidden', 'false');

        setTimeout(function() {
            $confirmAccept.trigger('focus');
        }, 10);
    }

    function confirmAndClose() {
        const callback = confirmCallback;
        closeConfirmModal();
        if (typeof callback === 'function') {
            callback();
        }
    }

    $confirmAccept.on('click', function() {
        confirmAndClose();
    });

    $confirmCancel.on('click', function() {
        closeConfirmModal();
    });

    $confirmClose.on('click', function() {
        closeConfirmModal();
    });

    $confirmModal.on('click', function(event) {
        if ($(event.target).is($confirmModal)) {
            closeConfirmModal();
        }
    });

    function formatLinkForDisplay(link) {
        if (!link) {
            return '';
        }

        try {
            const url = new URL(link);
            const display = url.host + url.pathname + url.search + url.hash;
            return display.length > 80 ? display.slice(0, 77) + '…' : display;
        } catch (error) {
            return link.length > 80 ? link.slice(0, 77) + '…' : link;
        }
    }

    function closeQrModal() {
        $qrModal.removeClass('is-visible').attr('aria-hidden', 'true');
        $qrContainer.empty();
        $qrLinkAnchor.attr('href', '#').text('');
        $qrLinkWrapper.show();
    }

    function openQrModal(options) {
        const settings = $.extend({
            name: '',
            link: '',
            qrData: ''
        }, options || {});

        $qrSubtitle.text(settings.name || '');

        if (settings.qrData) {
            const altText = settings.name
                ? settings.name + ' - <?php echo esc_js(__('QR code di pagamento', 'born-to-ride-booking')); ?>'
                : '<?php echo esc_js(__('QR code di pagamento', 'born-to-ride-booking')); ?>';
            const $image = $('<img>', {
                src: settings.qrData,
                alt: altText
            });
            $qrContainer.empty().append($image);
        } else {
            $qrContainer.empty().append(
                $('<p>', {
                    text: '<?php echo esc_js(__('QR code temporaneamente non disponibile', 'born-to-ride-booking')); ?>'
                })
            );
        }

        if (settings.link) {
            const formatted = formatLinkForDisplay(settings.link);
            $qrLinkAnchor.attr('href', settings.link).text(formatted);
            $qrLinkWrapper.show();
        } else {
            $qrLinkAnchor.attr('href', '#').text('');
            $qrLinkWrapper.hide();
        }

        $qrModal.addClass('is-visible').attr('aria-hidden', 'false');

        setTimeout(function() {
            $qrClose.trigger('focus');
        }, 10);
    }

    $qrClose.on('click', function() {
        closeQrModal();
    });

    $qrModal.on('click', function(event) {
        if ($(event.target).is($qrModal)) {
            closeQrModal();
        }
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape') {
            if ($confirmModal.hasClass('is-visible')) {
                closeConfirmModal();
            }

            if ($qrModal.hasClass('is-visible')) {
                closeQrModal();
            }
        }
    });

    $('.btr-filter-tab').on('click', function() {
        const $button = $(this);
        const filter = $button.data('filter');

        $('.btr-filter-tab').removeClass('active');
        $button.addClass('active');

        if (filter === 'all') {
            $('.btr-payment-card').show();
        } else {
            $('.btr-payment-card').hide();
            $('.btr-payment-card[data-status="' + filter + '"]').show();
        }
    });

    $('.btn-copy-link').on('click', function() {
        const link = $(this).data('link');
        const $button = $(this);

        const $temp = $('<input>');
        $('body').append($temp);
        $temp.val(link).select();
        document.execCommand('copy');
        $temp.remove();

        const originalHtml = $button.html();
        $button.text('<?php echo esc_js(__('Link copiato', 'born-to-ride-booking')); ?>');

        setTimeout(function() {
            $button.html(originalHtml);
        }, 2000);

        showToast('<?php echo esc_js(__('Link copiato negli appunti', 'born-to-ride-booking')); ?>', 'success');
    });

    $('.btn-send-email').on('click', function() {
        const $button = $(this);
        const paymentId = $button.data('payment-id');
        const originalHtml = $button.html();

        $button.prop('disabled', true).text('<?php echo esc_js(__('Invio...', 'born-to-ride-booking')); ?>');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'btr_send_payment_email',
                payment_id: paymentId,
                nonce: '<?php echo wp_create_nonce('btr_group_payments'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('<?php echo esc_js(__('Email inviata', 'born-to-ride-booking')); ?>');
                    showToast('<?php echo esc_js(__('Email inviata con successo', 'born-to-ride-booking')); ?>', 'success');
                    setTimeout(function() {
                        $button.prop('disabled', false).html(originalHtml);
                    }, 3000);
                } else {
                    const errorMessage = (response.data && response.data.message)
                        ? response.data.message
                        : '<?php echo esc_js(__('Errore nell\'invio dell\'email', 'born-to-ride-booking')); ?>';
                    showToast(errorMessage, 'error');
                    $button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                showToast('<?php echo esc_js(__('Errore di comunicazione', 'born-to-ride-booking')); ?>', 'error');
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    });

    function startBulkEmail($button) {
        const $emailButtons = $('.btn-send-email:not(:disabled)');
        const total = $emailButtons.length;
        let sent = 0;

        if (total === 0) {
            showToast('<?php echo esc_js(__('Nessuna email da inviare', 'born-to-ride-booking')); ?>', 'error');
            return;
        }

        const originalHtml = $button.html();
        $button.prop('disabled', true).text('<?php echo esc_js(__('Invio 0 di ', 'born-to-ride-booking')); ?>' + total);

        $emailButtons.each(function(index) {
            const $emailBtn = $(this);
            setTimeout(function() {
                $emailBtn.trigger('click');
                sent++;
                $button.text('<?php echo esc_js(__('Invio ', 'born-to-ride-booking')); ?>' + sent + ' di ' + total);

                if (sent === total) {
                    setTimeout(function() {
                        $button.prop('disabled', false).html(originalHtml);
                        showToast('<?php echo esc_js(__('Tutte le email sono state inviate', 'born-to-ride-booking')); ?>', 'success');
                    }, 1200);
                }
            }, index * 900);
        });
    }

    $('#send-all-emails').on('click', function() {
        const $button = $(this);

        if ($button.prop('disabled')) {
            return;
        }

        const pendingEmails = $('.btn-send-email:not(:disabled)').length;

        if (pendingEmails === 0) {
            showToast('<?php echo esc_js(__('Nessuna email da inviare', 'born-to-ride-booking')); ?>', 'error');
            return;
        }

        openConfirmModal({
            title: '<?php echo esc_js(__('Invia tutte le email?', 'born-to-ride-booking')); ?>',
            message: '<?php echo esc_js(__("Confermi l'invio dei link a ogni partecipante ancora in attesa?", 'born-to-ride-booking')); ?>',
            confirmLabel: '<?php echo esc_js(__('Invia', 'born-to-ride-booking')); ?>',
            cancelLabel: '<?php echo esc_js(__('Annulla', 'born-to-ride-booking')); ?>',
            onConfirm: function() {
                startBulkEmail($button);
            }
        });
    });

    function createOrganizerOrder($button, preventivoId) {
        const originalHtml = $button.html();
        $button.prop('disabled', true).text('<?php echo esc_js(__('Creazione ordine...', 'born-to-ride-booking')); ?>');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'btr_create_organizer_order',
                preventivo_id: preventivoId,
                nonce: '<?php echo wp_create_nonce('btr_payment_organizer_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        showToast('<?php echo esc_js(__('Ordine creato con successo', 'born-to-ride-booking')); ?>', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    const message = response.data && response.data.message
                        ? response.data.message
                        : '<?php echo esc_js(__("Errore nella creazione dell'ordine", 'born-to-ride-booking')); ?>';
                    showToast(message, 'error');
                    $button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                showToast('<?php echo esc_js(__('Errore di comunicazione con il server', 'born-to-ride-booking')); ?>', 'error');
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    }

    $('.js-organizer-proceed').on('click', function(event) {
        event.preventDefault();

        const $button = $(this);
        const preventivoId = $button.data('preventivo-id');

        if (!preventivoId) {
            showToast('<?php echo esc_js(__('Errore: ID preventivo mancante', 'born-to-ride-booking')); ?>', 'error');
            return;
        }

        openConfirmModal({
            title: '<?php echo esc_js(__('Concludi ordine?', 'born-to-ride-booking')); ?>',
            message: '<?php echo esc_js(__("Vuoi concludere l'ordine dell'organizzatore e aprire il checkout?", 'born-to-ride-booking')); ?>',
            confirmLabel: '<?php echo esc_js(__('Concludi ordine', 'born-to-ride-booking')); ?>',
            cancelLabel: '<?php echo esc_js(__('Annulla', 'born-to-ride-booking')); ?>',
            onConfirm: function() {
                createOrganizerOrder($button, preventivoId);
            }
        });
    });

    $('.btn-show-qr').on('click', function() {
        const $button = $(this);
        const link = $button.data('link');
        const name = $button.data('name');
        const qrData = $button.data('qr');

        openQrModal({
            name: name,
            link: link,
            qrData: qrData
        });
    });
});
</script>
