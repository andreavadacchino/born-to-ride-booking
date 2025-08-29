<?php
/**
 * Admin interface per gestione prezzi notte extra bambini
 * 
 * @since 1.0.15
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica autorizzazioni
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

$pricing_config = get_option('btr_child_extra_night_pricing', BTR_Child_Extra_Night_Pricing::DEFAULT_PRICING);

// Ottieni categorie child dinamiche se disponibili
$child_categories = [];
if (class_exists('BTR_Dynamic_Child_Categories')) {
    $dynamic_categories = new BTR_Dynamic_Child_Categories();
    $child_categories = $dynamic_categories->get_categories(true);
} else {
    // Fallback alle categorie predefinite
    $child_categories = [
        ['id' => 'f1', 'label' => 'Bambini 3-8 anni'],
        ['id' => 'f2', 'label' => 'Bambini 8-12 anni'],
        ['id' => 'f3', 'label' => 'Bambini 12-14 anni'],
        ['id' => 'f4', 'label' => 'Bambini 14-15 anni']
    ];
}
?>

<div class="wrap">
    <h1>Prezzi Notte Extra per Bambini</h1>
    
    <div class="btr-extra-night-pricing-header">
        <p>Configura i prezzi delle notti extra per ciascuna categoria di bambini. 
           Il sistema attuale usa un "metà prezzo" fisso che può essere personalizzato qui.</p>
        
        <div class="notice notice-info">
            <p><strong>💡 Nota:</strong> Queste configurazioni si applicano solo quando viene richiesta una notte extra. 
               Il prezzo base del pacchetto non viene influenzato.</p>
        </div>
    </div>

    <div class="btr-pricing-container">
        <div class="pricing-toolbar">
            <button id="save-pricing" class="button button-primary">💾 Salva Configurazioni</button>
            <button id="reset-pricing" class="button button-link-delete">🔄 Ripristina Default</button>
        </div>

        <div class="pricing-grid">
            <?php foreach ($child_categories as $category): ?>
                <?php 
                $category_id = $category['id'];
                $category_config = $pricing_config[$category_id] ?? BTR_Child_Extra_Night_Pricing::DEFAULT_PRICING['f1'];
                ?>
                <div class="pricing-card" data-category="<?= esc_attr($category_id) ?>">
                    <div class="pricing-card-header">
                        <h3><?= esc_html($category['label']) ?></h3>
                        <label class="pricing-toggle">
                            <input type="checkbox" class="category-enabled" 
                                   <?= $category_config['enabled'] ? 'checked' : '' ?>>
                            <span>Attivo</span>
                        </label>
                    </div>
                    
                    <div class="pricing-card-body">
                        <div class="pricing-type-section">
                            <label>Tipo di Prezzo:</label>
                            <select class="pricing-type">
                                <option value="percentage" <?= $category_config['pricing_type'] === 'percentage' ? 'selected' : '' ?>>
                                    Percentuale del prezzo adulto
                                </option>
                                <option value="fixed_discount" <?= $category_config['pricing_type'] === 'fixed_discount' ? 'selected' : '' ?>>
                                    Sconto fisso dal prezzo adulto
                                </option>
                                <option value="fixed_price" <?= $category_config['pricing_type'] === 'fixed_price' ? 'selected' : '' ?>>
                                    Prezzo fisso
                                </option>
                                <option value="free" <?= $category_config['pricing_type'] === 'free' ? 'selected' : '' ?>>
                                    Gratuito
                                </option>
                            </select>
                        </div>
                        
                        <div class="pricing-value-section" <?= $category_config['pricing_type'] === 'free' ? 'style="display:none"' : '' ?>>
                            <label class="pricing-value-label">Valore:</label>
                            <div class="pricing-value-input">
                                <input type="number" class="pricing-value" 
                                       value="<?= esc_attr($category_config['pricing_value']) ?>"
                                       min="0" step="0.01">
                                <span class="pricing-unit">%</span>
                            </div>
                            <p class="pricing-description">Percentuale del prezzo adulto</p>
                        </div>
                        
                        <div class="pricing-preview">
                            <h4>Anteprima</h4>
                            <div class="preview-calculation">
                                <div class="preview-row">
                                    <span>Prezzo adulto notte extra:</span>
                                    <span class="preview-adult">€30.00</span>
                                </div>
                                <div class="preview-row highlight">
                                    <span>Prezzo bambino:</span>
                                    <span class="preview-child">€15.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pricing-summary">
            <h3>Riepilogo Configurazioni</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Configurazione</th>
                        <th>Esempio (€30 adulto)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="summary-table">
                    <!-- Popolato dinamicamente -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.btr-extra-night-pricing-header {
    background: #f1f1f1;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #0073aa;
    border-radius: 4px;
}

.btr-pricing-container {
    background: white;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.pricing-toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.pricing-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
    transition: all 0.3s ease;
}

.pricing-card:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0,115,170,0.1);
}

.pricing-card.disabled {
    opacity: 0.6;
    background: #f0f0f1;
}

.pricing-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: white;
    border-bottom: 1px solid #ddd;
    border-radius: 8px 8px 0 0;
}

.pricing-card-header h3 {
    margin: 0;
    color: #0073aa;
    font-size: 16px;
}

.pricing-toggle {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 0;
}

.pricing-card-body {
    padding: 20px;
}

.pricing-type-section,
.pricing-value-section {
    margin-bottom: 20px;
}

.pricing-type-section label,
.pricing-value-label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.pricing-type {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pricing-value-input {
    display: flex;
    align-items: center;
    gap: 5px;
}

.pricing-value {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pricing-unit {
    font-weight: bold;
    color: #0073aa;
    min-width: 20px;
}

.pricing-description {
    margin: 5px 0 0;
    font-size: 12px;
    color: #666;
    font-style: italic;
}

.pricing-preview {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.pricing-preview h4 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #333;
}

.preview-calculation {
    font-family: monospace;
    font-size: 13px;
}

.preview-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
}

.preview-row.highlight {
    background: #e1ecf4;
    margin: 5px -10px;
    padding: 8px 10px;
    border-radius: 4px;
    font-weight: bold;
    color: #0073aa;
}

.pricing-summary {
    border-top: 1px solid #ddd;
    padding-top: 20px;
}

.status-active {
    color: #28a745;
    font-weight: bold;
}

.status-inactive {
    color: #dc3545;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    const pricingConfig = <?= json_encode($pricing_config) ?>;
    
    // Inizializza interfaccia
    updateAllPreviews();
    updateSummaryTable();
    
    // Event handlers
    $('.category-enabled').on('change', function() {
        const $card = $(this).closest('.pricing-card');
        $card.toggleClass('disabled', !$(this).is(':checked'));
        updateSummaryTable();
    });
    
    $('.pricing-type').on('change', function() {
        updatePricingUI($(this));
        updatePreview($(this).closest('.pricing-card'));
        updateSummaryTable();
    });
    
    $('.pricing-value').on('input', function() {
        updatePreview($(this).closest('.pricing-card'));
        updateSummaryTable();
    });
    
    $('#save-pricing').on('click', savePricingConfig);
    $('#reset-pricing').on('click', resetPricingConfig);
    
    function updatePricingUI($select) {
        const $card = $select.closest('.pricing-card');
        const $valueSection = $card.find('.pricing-value-section');
        const $unit = $card.find('.pricing-unit');
        const $description = $card.find('.pricing-description');
        const pricingType = $select.val();
        
        if (pricingType === 'free') {
            $valueSection.hide();
        } else {
            $valueSection.show();
            
            switch (pricingType) {
                case 'percentage':
                    $unit.text('%');
                    $description.text('Percentuale del prezzo adulto');
                    break;
                case 'fixed_discount':
                    $unit.text('€');
                    $description.text('Importo di sconto dal prezzo adulto');
                    break;
                case 'fixed_price':
                    $unit.text('€');
                    $description.text('Prezzo fisso per la notte extra');
                    break;
            }
        }
    }
    
    function updatePreview($card) {
        const category = $card.data('category');
        const enabled = $card.find('.category-enabled').is(':checked');
        const pricingType = $card.find('.pricing-type').val();
        const pricingValue = parseFloat($card.find('.pricing-value').val()) || 0;
        const adultPrice = 30; // Prezzo esempio
        
        let childPrice = 0;
        
        if (!enabled) {
            childPrice = adultPrice * 0.5; // Fallback
        } else {
            switch (pricingType) {
                case 'percentage':
                    childPrice = (adultPrice * pricingValue) / 100;
                    break;
                case 'fixed_discount':
                    childPrice = Math.max(0, adultPrice - pricingValue);
                    break;
                case 'fixed_price':
                    childPrice = pricingValue;
                    break;
                case 'free':
                    childPrice = 0;
                    break;
            }
        }
        
        $card.find('.preview-child').text('€' + childPrice.toFixed(2));
    }
    
    function updateAllPreviews() {
        $('.pricing-card').each(function() {
            updatePricingUI($(this).find('.pricing-type'));
            updatePreview($(this));
        });
    }
    
    function updateSummaryTable() {
        const $tbody = $('#summary-table');
        $tbody.empty();
        
        $('.pricing-card').each(function() {
            const $card = $(this);
            const category = $card.data('category');
            const categoryLabel = $card.find('h3').text();
            const enabled = $card.find('.category-enabled').is(':checked');
            const pricingType = $card.find('.pricing-type').val();
            const pricingValue = parseFloat($card.find('.pricing-value').val()) || 0;
            
            let configDisplay = '';
            let examplePrice = '';
            
            if (!enabled) {
                configDisplay = 'Disabilitato (usa fallback 50%)';
                examplePrice = '€15.00';
            } else {
                switch (pricingType) {
                    case 'percentage':
                        configDisplay = pricingValue + '% del prezzo adulto';
                        examplePrice = '€' + ((30 * pricingValue) / 100).toFixed(2);
                        break;
                    case 'fixed_discount':
                        configDisplay = '€' + pricingValue + ' di sconto';
                        examplePrice = '€' + Math.max(0, 30 - pricingValue).toFixed(2);
                        break;
                    case 'fixed_price':
                        configDisplay = '€' + pricingValue + ' fisso';
                        examplePrice = '€' + pricingValue.toFixed(2);
                        break;
                    case 'free':
                        configDisplay = 'Gratuito';
                        examplePrice = '€0.00';
                        break;
                }
            }
            
            const statusClass = enabled ? 'status-active' : 'status-inactive';
            const statusText = enabled ? '✅ Attivo' : '❌ Disabilitato';
            
            const row = `
                <tr>
                    <td><strong>${categoryLabel}</strong></td>
                    <td>${configDisplay}</td>
                    <td>${examplePrice}</td>
                    <td><span class="${statusClass}">${statusText}</span></td>
                </tr>
            `;
            
            $tbody.append(row);
        });
    }
    
    function collectPricingConfig() {
        const config = {};
        
        $('.pricing-card').each(function() {
            const $card = $(this);
            const category = $card.data('category');
            
            config[category] = {
                pricing_type: $card.find('.pricing-type').val(),
                pricing_value: parseFloat($card.find('.pricing-value').val()) || 0,
                enabled: $card.find('.category-enabled').is(':checked')
            };
        });
        
        return config;
    }
    
    function savePricingConfig() {
        if (!confirm('Salvare le configurazioni prezzi notte extra?')) {
            return;
        }
        
        const config = collectPricingConfig();
        const $button = $('#save-pricing');
        
        $button.prop('disabled', true).text('Salvando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_save_extra_night_pricing',
                pricing_config: JSON.stringify(config),
                nonce: '<?= wp_create_nonce("btr_child_extra_night_pricing") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                } else {
                    alert('❌ Errore: ' + (response.data.message || 'Errore sconosciuto'));
                    if (response.data.errors) {
                        console.error('Errori:', response.data.errors);
                    }
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione con il server');
            },
            complete: function() {
                $button.prop('disabled', false).text('💾 Salva Configurazioni');
            }
        });
    }
    
    function resetPricingConfig() {
        if (!confirm('Ripristinare le configurazioni default? Tutte le modifiche andranno perse.')) {
            return;
        }
        
        const $button = $('#reset-pricing');
        $button.prop('disabled', true).text('Ripristinando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_reset_extra_night_pricing',
                nonce: '<?= wp_create_nonce("btr_child_extra_night_pricing") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    location.reload();
                } else {
                    alert('❌ Errore: ' + response.data.message);
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione con il server');
            },
            complete: function() {
                $button.prop('disabled', false).text('🔄 Ripristina Default');
            }
        });
    }
});
</script>

<?php