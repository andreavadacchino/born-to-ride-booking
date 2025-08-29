<?php
// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Template del Metabox per il Pacchetto di Viaggio
 * Migliorato e reso robusto secondo le best practice di WordPress.
 */
// Estrae le variabili per un accesso più semplice
extract($meta_values);
?>
<div class="btr-metabox-container">
    <?php //wp_nonce_field('btr_save_metabox', 'btr_metabox_nonce'); ?>


    <div class="btr-meta-box-container">
        <div class="meta-box-sidebar">
            <ul class="meta-box-tabs">
                <li data-tab="settings" class="active" role="tab">Impostazioni</li>
                <li data-tab="date" role="tab">Date e disponibilità</li>
                <li data-tab="persone" role="tab">Persone</li>
                <li data-tab="camere" role="tab">Tipologie di camere</li>
                <li data-tab="varianti" role="tab">Riepilogo Varianti</li>
                <li data-tab="badge" role="tab">Badge Giacenze</li>
                <li data-tab="assicurazioni">Assicurazioni</li>
                <li data-tab="extra-costs">Costi Extra</li>
                <li data-tab="preventivo-settings">Impostazioni Preventivo</li>
                </ul>
        </div>
        <div class="meta-box-content">

            <div id="settings" class="tab-content active" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/settings.php'; ?>
            </div>

            <div id="date" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/date.php'; ?>
            </div>

            <div id="persone" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/persone.php'; ?>
            </div>

            <div id="camere" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/camere.php'; ?>
            </div>

            <div id="varianti" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/varianti.php'; ?>
            </div>

            <div id="badge" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/badge.php'; ?>
            </div>


            <div id="extra-costs" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/extra-costs.php'; ?>
            </div>


            <div id="assicurazioni" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/assicurazioni.php'; ?>
            </div>

            <div id="preventivo-settings" class="tab-content" role="tabpanel">
                <?php include BTR_PLUGIN_DIR.'templates/admin/metabox-pacchetto-tab/preventivo-settings.php'; ?>
            </div>


        </div>
    </div>


    <style>
.btr-badge-preview {
    display: inline-block;
    vertical-align: middle;
    margin-left: 8px;
}
.btr-badge-preview span {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
}
/* Badge color definitions */
.btr-badge-soldout span {
    background: linear-gradient(135deg, #9e0b0f 0%, #cc1c1e 100%);
}
.btr-badge-last-one span {
    background: linear-gradient(135deg, #ff9800 0%, #ef6c00 100%);
}
.btr-badge-few-left span {
    background: linear-gradient(135deg, #ffb74d 0%, #ffa726 100%);
}
.btr-badge-info span {
    background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
}
.btr-badge-available span {
    background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
}
.btr-badge-gray-light span {
    background: #f5f5f5;
}
.btr-badge-gray-dark span {
    background: #616161;
}
.btr-badge-black span {
    background: #000000;
}
.btr-badge-purple span {
    background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
}
.btr-badge-teal span {
    background: linear-gradient(135deg, #009688 0%, #00796b 100%);
}
.btr-badge-yellow span {
    background: linear-gradient(135deg, #ffeb3b 0%, #fbc02d 100%);
    color: #333;
}
.btr-badge-pink span {
    background: linear-gradient(135deg, #ec407a 0%, #d81b60 100%);
}
.btr-badge-indigo span {
    background: linear-gradient(135deg, #3f51b5 0%, #303f9f 100%);
}
.btr-badge-lime span {
    background: linear-gradient(135deg, #cddc39 0%, #afb42b 100%);
    color: #333;
}
.btr-badge-brown span {
    background: linear-gradient(135deg, #795548 0%, #5d4037 100%);
}
.btr-variant-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
}
.btr-variant-table thead {
    background: #f8f9fb;
    color: #333;
}
.btr-variant-table th, .btr-variant-table td {
    border: 1px solid #e0e0e0;
    padding: 10px 12px;
    text-align: left;
}
.btr-variant-table tr:nth-child(even) {
    background: #f9f9f9;
}
.btr-variant-table tr:hover {
    background: #eef4ff;
}
.btr-variant-table td strong {
    color: #2271b1;
}
.m-0 {
    margin: 0;
}
/* Modern WooCommerce button style */
.btr-modern-button {
    display: inline-block;
    background: linear-gradient(135deg, #4a90e2, #2271b1);
    color: #fff !important;
    padding: 10px 18px;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.3s ease;
}

.btr-modern-button:hover {
    background: linear-gradient(135deg, #2271b1, #2271b1);
    text-decoration: none;
    color: #fff !important;
}
        .row-children {
            display: flex;
            justify-content: space-between;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
        }
        .btr-children-discount {
            margin-left: auto;
            margin-right: 2em;
        }
        .btr-meta-box-container {
            display: flex;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }


        .meta-box-sidebar {
            flex: 0 0 200px;
            background: linear-gradient(135deg, #f9fafb, #f5f7fa);
            border-right: 1px solid #e0e0e0;
            padding: 15px 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .meta-box-tabs {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .meta-box-tabs li {
            padding: 10px 20px;
            cursor: pointer;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            color: #333;
            font-size: 14px;
            font-weight: 400;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
        }

        .meta-box-tabs li:hover {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }

        .meta-box-tabs li.active {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .meta-box-content {
            flex: 1;
            overflow-y: auto;
            background: #f9fafb;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        }

        .tab-content.active {
            display: block;
            background: #ffffff;
            padding: 1em;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .btr-meta-box-container {
                flex-direction: column;
            }

            .meta-box-sidebar {
                flex: 0 0 auto;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
                align-items: flex-start;
            }

            .meta-box-content {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .meta-box-tabs li {
                padding: 10px 15px;
                font-size: 14px;
            }

            .meta-box-content {
                padding: 15px;
            }
        }

.btr-product-header {
    font-size: 16px;
    padding: 10px 0;
    margin-bottom: 12px;
    color: #333;
}
.btr-product-id {
    font-weight: normal;
    color: #888;
    margin-left: 6px;
}

.btr-variant-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
    font-size: 14px;
}

.btr-variant-table thead {
    background-color: #f1f3f6;
    color: #555;
    text-align: left;
}

.btr-variant-table th,
.btr-variant-table td {
    padding: 10px 12px;
    background: #fff;
    border: 1px solid #e4e7ec;
}

.btr-variant-table td:nth-child(4),
.btr-variant-table td:nth-child(5) {
    font-weight: bold;
}

.btr-variant-table td:nth-child(4) {
    color: #2563eb; /* blu */
}

.btr-variant-table td:nth-child(5) {
    color: #d97706; /* arancio */
}
    </style>

    <script>
        jQuery(document).ready(function ($) {
            // Click event for tabs
            $('.meta-box-tabs li').on('click', function () {
                const tab = $(this).data('tab');

                // Manage active tab classes
                $('.meta-box-tabs li').removeClass('active');
                $(this).addClass('active');

                // Switch tab content
                $('.tab-content').removeClass('active');
                $('#' + tab).addClass('active');
            });

            // Accessibility: Keyboard navigation for tabs
            $('.meta-box-tabs li').on('keydown', function (e) {
                const key = e.key;

                // Navigate with arrow keys
                if (key === 'ArrowDown' || key === 'ArrowRight') {
                    $(this).next().focus().click();
                } else if (key === 'ArrowUp' || key === 'ArrowLeft') {
                    $(this).prev().focus().click();
                }
            });
            // Anteprima colore badge
            function updateBadgePreview($select) {
                var cls = $select.val() || '';
                var $preview = $select.next('.btr-badge-preview');
                $preview.attr('class', 'btr-badge-preview' + (cls ? ' ' + cls : ''));
            }
            $('select[name^="btr_badge_rules"][name$="[class]"]').each(function() {
                var $sel = $(this);
                updateBadgePreview($sel);
                $sel.on('change', function() {
                    updateBadgePreview($sel);
                });
            });
        });

        // Aggiungi nuova regola badge (binding jQuery DOM ready)
        jQuery(document).ready(function ($) {
            // Funzione di anteprima badge (necessaria anche per regole dinamiche)
            function updateBadgePreview($select) {
                var cls = $select.val() || '';
                var $preview = $select.next('.btr-badge-preview');
                $preview.attr('class', 'btr-badge-preview' + (cls ? ' ' + cls : ''));
            }
            $('#add-badge-rule').on('click', function () {
                const container = $('#btr-badge-rules-container');
                const index = container.find('.btr-badge-rule').length;

                const template = `
                <div class="btr-badge-rule btr-extra-cost-card">
                    <span class="dashicons dashicons-move drag-handle"></span>
                    <div class="btr-extra-cost-fields">
                        <div class="btr-field-group">
                            <label>Condizione</label>
                            <select name="btr_badge_rules[${index}][condizione]">
                                <option value="eq">Uguale a</option>
                                <option value="lt">Minore di</option>
                                <option value="lte">Minore o uguale a</option>
                            </select>
                        </div>
                        <div class="btr-field-group">
                            <label>Soglia</label>
                            <input type="number" name="btr_badge_rules[${index}][soglia]" value="0" min="0" />
                        </div>
                        <div class="btr-field-group">
                            <label>Etichetta</label>
                            <input type="text" name="btr_badge_rules[${index}][label]" value="" placeholder="Es. Ultimi Posti" />
                        </div>
                        <div class="btr-field-group">
                            <label>Classe CSS</label>
<div class="container-select-badge">
                            <select name="btr_badge_rules[${index}][class]">
                              <option value="">-- Seleziona uno stile --</option>
                              <option value="btr-badge-soldout">Rosso (Esaurito / Sold Out)</option>
                              <option value="btr-badge-last-one">Arancione (Ultima Camera)</option>
                              <option value="btr-badge-few-left">Rosso Chiaro (Ultime Camere)</option>
                              <option value="btr-badge-info">Blu (Informativo)</option>
                              <option value="btr-badge-available">Verde (Disponibile)</option>
                              <option value="btr-badge-gray-light">Grigio Chiaro</option>
                              <option value="btr-badge-gray-dark">Grigio Scuro</option>
                              <option value="btr-badge-black">Nero</option>
                              <option value="btr-badge-purple">Viola</option>
                              <option value="btr-badge-teal">Teal</option>
                              <option value="btr-badge-yellow">Giallo</option>
                              <option value="btr-badge-pink">Rosa</option>
                              <option value="btr-badge-indigo">Indaco</option>
                              <option value="btr-badge-lime">Lime</option>
                              <option value="btr-badge-brown">Marrone</option>
                            </select>
                            <span class="btr-badge-preview"><span></span></span>
</div>
                        </div>
                        <div class="btr-switch-container">
                            <label for="btr_badge_enabled_${index}">Attivo</label>
                            <input type="checkbox" id="btr_badge_enabled_${index}" name="btr_badge_rules[${index}][enabled]" value="1" checked />

                            <label class="btr-switch" for="btr_badge_enabled_${index}">
                                <span class="btr-switch-handle">
                                    <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </span>
                            </label>
                        </div>
                    </div>
                    <button type="button" class="remove-item button">Rimuovi</button>
                </div>`;
                container.append(template);
                // Inizializza anteprima colore sulla nuova regola
                const $newSelect = container.find(`select[name="btr_badge_rules[${index}][class]"]`);
                updateBadgePreview($newSelect);
                $newSelect.on('change', function() { updateBadgePreview($newSelect); });
            });
        });

        // Rimuovi regola badge
        document.addEventListener('click', function (e) {
            if (e.target.closest('.remove-item') && e.target.closest('.btr-badge-rule')) {
                e.target.closest('.btr-badge-rule').remove();
            }
        });
    </script>


</div>