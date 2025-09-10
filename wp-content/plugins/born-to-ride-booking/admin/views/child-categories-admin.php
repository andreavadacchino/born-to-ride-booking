<?php
/**
 * Admin interface per gestione categorie child dinamiche
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

$categories = get_option('btr_child_categories', BTR_Dynamic_Child_Categories::DEFAULT_CATEGORIES);

// Verifica conflitti di range età
$dynamic_cats = new BTR_Dynamic_Child_Categories();
$conflicts = $dynamic_cats->check_age_conflicts($categories);
?>

<div class="wrap">
    <h1>Gestione Categorie Bambini</h1>
    
    <div class="btr-child-categories-header">
        <p>Configura le categorie di età per i bambini. Ogni categoria può avere un range di età specifico e uno sconto personalizzato.</p>
        
        <?php if (!empty($conflicts)): ?>
        <div class="notice notice-warning">
            <h4>⚠️ Attenzione: Conflitti di Range Età Rilevati</h4>
            <?php foreach ($conflicts as $conflict): ?>
            <p><strong><?= esc_html($conflict['category1']) ?></strong> (<?= esc_html($conflict['range1']) ?>) si sovrappone con 
               <strong><?= esc_html($conflict['category2']) ?></strong> (<?= esc_html($conflict['range2']) ?>)</p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="btr-categories-container">
        <div class="categories-toolbar">
            <button id="add-category" class="button button-secondary">➕ Aggiungi Categoria</button>
            <button id="reset-categories" class="button button-link-delete">🔄 Ripristina Default</button>
            <button id="save-categories" class="button button-primary">💾 Salva Configurazioni</button>
        </div>

        <div id="categories-list" class="categories-list">
            <!-- Le categorie saranno renderizzate qui via JavaScript -->
        </div>

        <div class="categories-preview">
            <h3>Anteprima Frontend</h3>
            <div id="frontend-preview" class="frontend-preview">
                <!-- Anteprima generata dinamicamente -->
            </div>
        </div>
    </div>
</div>

<script type="text/template" id="category-template">
    <div class="category-item" data-category-id="{categoryId}">
        <div class="category-header">
            <div class="category-drag-handle">⋮⋮</div>
            <div class="category-info">
                <strong class="category-label">{label}</strong>
                <span class="category-range">{ageMin}-{ageMax} anni</span>
                <span class="category-discount">{discountDisplay}</span>
            </div>
            <div class="category-controls">
                <label class="category-toggle">
                    <input type="checkbox" class="category-enabled" {checkedStatus}>
                    <span>Attiva</span>
                </label>
                <button type="button" class="button-link category-edit">✏️</button>
                <button type="button" class="button-link category-delete">🗑️</button>
            </div>
        </div>
        
        <div class="category-form" style="display: none;">
            <table class="form-table">
                <tr>
                    <th><label>ID Categoria</label></th>
                    <td>
                        <input type="text" class="category-id" value="{categoryId}" maxlength="10" pattern="[a-z0-9_-]+" required>
                        <p class="description">Solo lettere minuscole, numeri, underscore e trattini</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Etichetta</label></th>
                    <td>
                        <input type="text" class="category-label-input" value="{label}" maxlength="50" required>
                        <p class="description">Nome mostrato agli utenti</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Range Età</label></th>
                    <td>
                        <input type="number" class="category-age-min" value="{ageMin}" min="0" max="17" required> anni -
                        <input type="number" class="category-age-max" value="{ageMax}" min="1" max="18" required> anni
                        <p class="description">Range di età per questa categoria</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Tipo Sconto</label></th>
                    <td>
                        <select class="category-discount-type">
                            <option value="percentage" {selectedPercentage}>Percentuale (%)</option>
                            <option value="fixed" {selectedFixed}>Sconto fisso (€)</option>
                            <option value="absolute" {selectedAbsolute}>Prezzo assoluto (€)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Valore Sconto</label></th>
                    <td>
                        <input type="number" class="category-discount-value" value="{discountValue}" min="0" step="0.01" required>
                        <span class="discount-unit">{discountUnit}</span>
                        <p class="description discount-description">{discountDescription}</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Ordine</label></th>
                    <td>
                        <input type="number" class="category-order" value="{order}" min="1" max="99">
                        <p class="description">Ordine di visualizzazione (numeri più bassi = mostrati prima)</p>
                    </td>
                </tr>
            </table>
            
            <div class="category-form-actions">
                <button type="button" class="button button-primary category-save">💾 Salva</button>
                <button type="button" class="button category-cancel">❌ Annulla</button>
            </div>
        </div>
    </div>
</script>

<style>
.btr-child-categories-header {
    background: #f1f1f1;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #0073aa;
    border-radius: 4px;
}

.btr-categories-container {
    background: white;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.categories-toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
    align-items: center;
}

.categories-list {
    margin-bottom: 30px;
}

.category-item {
    background: #fafafa;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
    transition: background 0.2s;
}

.category-item:hover {
    background: #f0f0f1;
}

.category-item.disabled {
    opacity: 0.6;
}

.category-header {
    display: flex;
    align-items: center;
    padding: 15px;
    cursor: pointer;
}

.category-drag-handle {
    margin-right: 10px;
    color: #666;
    cursor: grab;
    font-size: 16px;
}

.category-drag-handle:active {
    cursor: grabbing;
}

.category-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 15px;
}

.category-label {
    font-size: 16px;
    color: #0073aa;
}

.category-range {
    background: #e1ecf4;
    color: #0073aa;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.category-discount {
    background: #d4edda;
    color: #155724;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.category-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-toggle {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 0;
}

.category-form {
    border-top: 1px solid #ddd;
    padding: 20px;
    background: white;
}

.category-form-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.categories-preview {
    border-top: 1px solid #ddd;
    padding-top: 20px;
}

.frontend-preview {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-top: 10px;
}

.preview-category {
    display: inline-block;
    margin: 5px;
    padding: 8px 12px;
    background: #0073aa;
    color: white;
    border-radius: 4px;
    font-size: 14px;
}

.preview-category.disabled {
    background: #ccc;
}

.button-link:hover {
    color: #d63384;
}

.sortable-placeholder {
    background: #e1ecf4;
    border: 2px dashed #0073aa;
    height: 60px;
    margin-bottom: 10px;
    border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    const categories = <?= json_encode($categories) ?>;
    let nextCategoryId = Math.max(...categories.map(c => parseInt(c.id.replace(/\D/g, '')) || 0)) + 1;
    
    // Inizializza la pagina
    renderCategories();
    updatePreview();
    
    // Rende la lista ordinabile
    $('.categories-list').sortable({
        handle: '.category-drag-handle',
        placeholder: 'sortable-placeholder',
        update: function() {
            updateCategoriesOrder();
        }
    });
    
    // Event handlers
    $('#add-category').on('click', addNewCategory);
    $('#save-categories').on('click', saveCategories);
    $('#reset-categories').on('click', resetCategories);
    
    $(document).on('click', '.category-header', function(e) {
        if (!$(e.target).closest('.category-controls').length) {
            toggleCategoryForm($(this).closest('.category-item'));
        }
    });
    
    $(document).on('click', '.category-edit', function() {
        toggleCategoryForm($(this).closest('.category-item'));
    });
    
    $(document).on('click', '.category-delete', function() {
        deleteCategory($(this).closest('.category-item'));
    });
    
    $(document).on('click', '.category-save', function() {
        saveCategoryForm($(this).closest('.category-item'));
    });
    
    $(document).on('click', '.category-cancel', function() {
        cancelCategoryForm($(this).closest('.category-item'));
    });
    
    $(document).on('change', '.category-enabled', function() {
        updatePreview();
    });
    
    $(document).on('change', '.category-discount-type', function() {
        updateDiscountUnit($(this));
    });
    
    function renderCategories() {
        const $list = $('#categories-list');
        $list.empty();
        
        categories.forEach(function(category) {
            const $item = createCategoryElement(category);
            $list.append($item);
        });
    }
    
    function createCategoryElement(category) {
        const template = $('#category-template').html();
        
        const discountDisplay = getDiscountDisplay(category);
        const discountUnit = getDiscountUnit(category.discount_type);
        const discountDescription = getDiscountDescription(category.discount_type);
        
        let html = template
            .replace(/{categoryId}/g, category.id)
            .replace(/{label}/g, category.label)
            .replace(/{ageMin}/g, category.age_min)
            .replace(/{ageMax}/g, category.age_max)
            .replace(/{discountDisplay}/g, discountDisplay)
            .replace(/{discountValue}/g, category.discount_value)
            .replace(/{order}/g, category.order)
            .replace(/{checkedStatus}/g, category.enabled ? 'checked' : '')
            .replace(/{selectedPercentage}/g, category.discount_type === 'percentage' ? 'selected' : '')
            .replace(/{selectedFixed}/g, category.discount_type === 'fixed' ? 'selected' : '')
            .replace(/{selectedAbsolute}/g, category.discount_type === 'absolute' ? 'selected' : '')
            .replace(/{discountUnit}/g, discountUnit)
            .replace(/{discountDescription}/g, discountDescription);
            
        const $element = $(html);
        if (!category.enabled) {
            $element.addClass('disabled');
        }
        
        return $element;
    }
    
    function getDiscountDisplay(category) {
        switch (category.discount_type) {
            case 'percentage':
                return '-' + category.discount_value + '%';
            case 'fixed':
                return '-€' + category.discount_value;
            case 'absolute':
                return '€' + category.discount_value;
            default:
                return '';
        }
    }
    
    function getDiscountUnit(discountType) {
        switch (discountType) {
            case 'percentage': return '%';
            case 'fixed': return '€ di sconto';
            case 'absolute': return '€ prezzo finale';
            default: return '';
        }
    }
    
    function getDiscountDescription(discountType) {
        switch (discountType) {
            case 'percentage': return 'Percentuale di sconto sul prezzo adulto';
            case 'fixed': return 'Importo fisso di sconto dal prezzo adulto';
            case 'absolute': return 'Prezzo finale fisso indipendente dal prezzo adulto';
            default: return '';
        }
    }
    
    function addNewCategory() {
        const newCategory = {
            id: 'f' + nextCategoryId,
            label: 'Nuova Categoria',
            age_min: 3,
            age_max: 6,
            discount_type: 'percentage',
            discount_value: 20,
            enabled: true,
            order: categories.length + 1
        };
        
        categories.push(newCategory);
        nextCategoryId++;
        
        const $item = createCategoryElement(newCategory);
        $('#categories-list').append($item);
        
        // Apri automaticamente il form per la modifica
        toggleCategoryForm($item);
        updatePreview();
    }
    
    function toggleCategoryForm($item) {
        const $form = $item.find('.category-form');
        $form.slideToggle();
    }
    
    function saveCategoryForm($item) {
        const categoryId = $item.data('category-id');
        const categoryIndex = categories.findIndex(c => c.id === categoryId);
        
        if (categoryIndex === -1) return;
        
        // Raccogli dati dal form
        const formData = {
            id: $item.find('.category-id').val(),
            label: $item.find('.category-label-input').val(),
            age_min: parseInt($item.find('.category-age-min').val()),
            age_max: parseInt($item.find('.category-age-max').val()),
            discount_type: $item.find('.category-discount-type').val(),
            discount_value: parseFloat($item.find('.category-discount-value').val()),
            order: parseInt($item.find('.category-order').val()),
            enabled: $item.find('.category-enabled').is(':checked')
        };
        
        // Validazione base
        if (!formData.label.trim()) {
            alert('Etichetta richiesta');
            return;
        }
        
        if (formData.age_min >= formData.age_max) {
            alert('Età minima deve essere inferiore a età massima');
            return;
        }
        
        // Aggiorna categoria
        categories[categoryIndex] = formData;
        
        // Aggiorna l'elemento
        const $newItem = createCategoryElement(formData);
        $item.replaceWith($newItem);
        
        updatePreview();
        
        alert('Categoria aggiornata. Ricorda di salvare le configurazioni.');
    }
    
    function cancelCategoryForm($item) {
        $item.find('.category-form').slideUp();
    }
    
    function deleteCategory($item) {
        if (!confirm('Sei sicuro di voler eliminare questa categoria?')) {
            return;
        }
        
        const categoryId = $item.data('category-id');
        const categoryIndex = categories.findIndex(c => c.id === categoryId);
        
        if (categoryIndex !== -1) {
            categories.splice(categoryIndex, 1);
            $item.remove();
            updatePreview();
        }
    }
    
    function updateCategoriesOrder() {
        $('#categories-list .category-item').each(function(index) {
            const categoryId = $(this).data('category-id');
            const category = categories.find(c => c.id === categoryId);
            if (category) {
                category.order = index + 1;
            }
        });
    }
    
    function updateDiscountUnit($select) {
        const $item = $select.closest('.category-item');
        const discountType = $select.val();
        const unit = getDiscountUnit(discountType);
        const description = getDiscountDescription(discountType);
        
        $item.find('.discount-unit').text(unit);
        $item.find('.discount-description').text(description);
    }
    
    function updatePreview() {
        const $preview = $('#frontend-preview');
        $preview.empty();
        
        categories
            .filter(c => c.enabled)
            .sort((a, b) => (a.order || 999) - (b.order || 999))
            .forEach(function(category) {
                const $previewItem = $(`
                    <div class="preview-category">
                        ${category.label} (${category.age_min}-${category.age_max} anni)
                        ${getDiscountDisplay(category)}
                    </div>
                `);
                $preview.append($previewItem);
            });
            
        if ($preview.children().length === 0) {
            $preview.html('<p style="color: #666;">Nessuna categoria attiva</p>');
        }
    }
    
    function saveCategories() {
        if (!confirm('Salvare le configurazioni delle categorie bambini?')) {
            return;
        }
        
        $('#save-categories').prop('disabled', true).text('Salvando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_save_child_categories',
                categories: JSON.stringify(categories),
                nonce: '<?= wp_create_nonce("btr_child_categories") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                } else {
                    alert('❌ Errore: ' + (response.data.message || 'Errore sconosciuto'));
                    if (response.data.errors) {
                        console.error('Errori di validazione:', response.data.errors);
                    }
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione con il server');
            },
            complete: function() {
                $('#save-categories').prop('disabled', false).text('💾 Salva Configurazioni');
            }
        });
    }
    
    function resetCategories() {
        if (!confirm('Ripristinare le configurazioni default? Tutte le modifiche andranno perse.')) {
            return;
        }
        
        $('#reset-categories').prop('disabled', true).text('Ripristinando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_reset_child_categories',
                nonce: '<?= wp_create_nonce("btr_child_categories") ?>'
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
                $('#reset-categories').prop('disabled', false).text('🔄 Ripristina Default');
            }
        });
    }
});
</script>

<?php