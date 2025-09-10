<?php
/**
 * Admin interface per impostazioni validazione età bambini
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

$settings = get_option('btr_age_validation_settings', []);
?>

<div class="wrap">
    <h1>Validazione Età Bambini alla Partenza</h1>
    
    <div class="btr-age-validation-header">
        <p>Configura il sistema di validazione per verificare che l'età dei bambini al momento della partenza 
           corrisponda alle categorie child selezionate nella prenotazione.</p>
        
        <div class="notice notice-info">
            <p><strong>💡 Come funziona:</strong> Il sistema calcola l'età del bambino alla data di partenza del viaggio 
               e verifica se corrisponde alla categoria selezionata (es. F1: 3-6 anni).</p>
        </div>
    </div>

    <form id="age-validation-settings-form">
        <div class="btr-validation-container">
            
            <!-- Impostazioni Generali -->
            <div class="settings-section">
                <h2>Impostazioni Generali</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="validation-enabled">Abilita Validazione</label>
                        </th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" id="validation-enabled" name="enabled" 
                                       <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="description">Attiva il sistema di validazione età bambini</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="strict-validation">Validazione Rigorosa</label>
                        </th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" id="strict-validation" name="strict_validation" 
                                       <?= !empty($settings['strict_validation']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="description">Se attiva, blocca la prenotazione in caso di incongruenze. 
                               Altrimenti mostra solo avvisi.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="show-warnings">Mostra Avvisi</label>
                        </th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" id="show-warnings" name="show_warnings" 
                                       <?= !empty($settings['show_warnings']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="description">Mostra avvisi all'utente quando ci sono incongruenze</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto-suggest">Suggerimenti Automatici</label>
                        </th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" id="auto-suggest" name="auto_suggest_corrections" 
                                       <?= !empty($settings['auto_suggest_corrections']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="description">Suggerisce automaticamente la categoria corretta</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Tolleranza Età -->
            <div class="settings-section">
                <h2>Tolleranza Età</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="allow-tolerance">Permetti Tolleranza</label>
                        </th>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" id="allow-tolerance" name="allow_age_tolerance" 
                                       <?= !empty($settings['allow_age_tolerance']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="description">Permette una tolleranza di età per categorie limite</p>
                        </td>
                    </tr>
                    
                    <tr class="tolerance-row" <?= empty($settings['allow_age_tolerance']) ? 'style="display:none"' : '' ?>>
                        <th scope="row">
                            <label for="tolerance-months">Tolleranza (mesi)</label>
                        </th>
                        <td>
                            <input type="number" id="tolerance-months" name="tolerance_months" 
                                   value="<?= esc_attr($settings['tolerance_months'] ?? 3) ?>" 
                                   min="1" max="12" class="small-text">
                            <p class="description">Numero di mesi di tolleranza per categorie limite</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Tipo Notifiche -->
            <div class="settings-section">
                <h2>Notifiche</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="notification-type">Tipo Notifica</label>
                        </th>
                        <td>
                            <select id="notification-type" name="notification_type">
                                <option value="info" <?= ($settings['notification_type'] ?? '') === 'info' ? 'selected' : '' ?>>
                                    Info (blu)
                                </option>
                                <option value="warning" <?= ($settings['notification_type'] ?? 'warning') === 'warning' ? 'selected' : '' ?>>
                                    Avviso (giallo)
                                </option>
                                <option value="error" <?= ($settings['notification_type'] ?? '') === 'error' ? 'selected' : '' ?>>
                                    Errore (rosso)
                                </option>
                            </select>
                            <p class="description">Tipo di notifica da mostrare all'utente</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Test Validazione -->
            <div class="settings-section">
                <h2>Test Validazione</h2>
                
                <div class="test-validation-area">
                    <h4>Testa il Sistema di Validazione</h4>
                    <div class="test-inputs">
                        <div class="test-input-group">
                            <label>Data di Nascita Bambino:</label>
                            <input type="date" id="test-birth-date" value="2018-05-15">
                        </div>
                        <div class="test-input-group">
                            <label>Data Partenza Viaggio:</label>
                            <input type="date" id="test-departure-date" value="<?= date('Y-m-d', strtotime('+2 months')) ?>">
                        </div>
                        <div class="test-input-group">
                            <label>Categoria Selezionata:</label>
                            <select id="test-category">
                                <option value="f1">F1 - Bambini 3-8 anni</option>
                                <option value="f2">F2 - Bambini 8-12 anni</option>
                                <option value="f3">F3 - Bambini 12-14 anni</option>
                                <option value="f4">F4 - Bambini 14-15 anni</option>
                            </select>
                        </div>
                        <button type="button" id="run-test" class="button">Esegui Test</button>
                    </div>
                    
                    <div id="test-results" class="test-results" style="display:none;">
                        <!-- Risultati test -->
                    </div>
                </div>
            </div>
            
            <!-- Azioni -->
            <div class="settings-actions">
                <button type="submit" class="button button-primary">💾 Salva Impostazioni</button>
                <button type="button" id="reset-settings" class="button button-secondary">🔄 Ripristina Default</button>
            </div>
        </div>
    </form>
</div>

<style>
.btr-age-validation-header {
    background: #f1f1f1;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #0073aa;
    border-radius: 4px;
}

.btr-validation-container {
    background: white;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.settings-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.settings-section:last-of-type {
    border-bottom: none;
}

.settings-section h2 {
    margin-top: 0;
    color: #0073aa;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

/* Toggle Switch Styling */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #0073aa;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

/* Test Area */
.test-validation-area {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
}

.test-inputs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
    align-items: end;
}

.test-input-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.test-input-group input,
.test-input-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.test-results {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.test-result-item {
    margin-bottom: 10px;
    padding: 10px;
    border-radius: 4px;
}

.test-result-item.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.test-result-item.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.test-result-item.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.settings-actions {
    border-top: 1px solid #ddd;
    padding-top: 20px;
    display: flex;
    gap: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle tolleranza visibility
    $('#allow-tolerance').on('change', function() {
        $('.tolerance-row').toggle($(this).is(':checked'));
    });
    
    // Form submission
    $('#age-validation-settings-form').on('submit', function(e) {
        e.preventDefault();
        saveSettings();
    });
    
    // Reset settings
    $('#reset-settings').on('click', function() {
        if (confirm('Ripristinare le impostazioni default?')) {
            resetSettings();
        }
    });
    
    // Test validation
    $('#run-test').on('click', runValidationTest);
    
    function saveSettings() {
        const $form = $('#age-validation-settings-form');
        const formData = new FormData($form[0]);
        formData.append('action', 'btr_save_age_validation_settings');
        formData.append('nonce', '<?= wp_create_nonce("btr_age_validation_settings") ?>');
        
        const $button = $form.find('button[type="submit"]');
        $button.prop('disabled', true).text('Salvando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                } else {
                    alert('❌ Errore: ' + response.data.message);
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione con il server');
            },
            complete: function() {
                $button.prop('disabled', false).text('💾 Salva Impostazioni');
            }
        });
    }
    
    function resetSettings() {
        // Implementa reset delle impostazioni
        location.reload();
    }
    
    function runValidationTest() {
        const birthDate = $('#test-birth-date').val();
        const departureDate = $('#test-departure-date').val();
        const category = $('#test-category').val();
        
        if (!birthDate || !departureDate) {
            alert('Inserisci entrambe le date per il test');
            return;
        }
        
        const birth = new Date(birthDate);
        const departure = new Date(departureDate);
        
        // Calcola età
        const ageAtDeparture = calculateAge(birth, departure);
        const categoryRanges = {
            'f1': {min: 3, max: 8, label: 'Bambini 3-8 anni'},
            'f2': {min: 8, max: 12, label: 'Bambini 8-12 anni'},
            'f3': {min: 12, max: 14, label: 'Bambini 12-14 anni'},
            'f4': {min: 14, max: 15, label: 'Bambini 14-15 anni'}
        };
        
        const selectedRange = categoryRanges[category];
        const isValid = ageAtDeparture.years >= selectedRange.min && ageAtDeparture.years < selectedRange.max;
        
        // Trova categoria corretta
        let correctCategory = null;
        for (const catId in categoryRanges) {
            const range = categoryRanges[catId];
            if (ageAtDeparture.years >= range.min && ageAtDeparture.years < range.max) {
                correctCategory = {id: catId, ...range};
                break;
            }
        }
        
        // Mostra risultati
        displayTestResults(ageAtDeparture, selectedRange, isValid, correctCategory);
    }
    
    function calculateAge(birth, departure) {
        const diff = departure - birth;
        const ageDate = new Date(diff);
        const years = ageDate.getUTCFullYear() - 1970;
        const months = ageDate.getUTCMonth();
        const days = ageDate.getUTCDate() - 1;
        
        return {years, months, days};
    }
    
    function displayTestResults(age, selectedRange, isValid, correctCategory) {
        const $results = $('#test-results');
        
        let html = '<h4>Risultati Test</h4>';
        
        html += `<div class="test-result-item">`;
        html += `<strong>Età alla partenza:</strong> ${age.years} anni, ${age.months} mesi, ${age.days} giorni`;
        html += `</div>`;
        
        html += `<div class="test-result-item">`;
        html += `<strong>Categoria selezionata:</strong> ${selectedRange.label}`;
        html += `</div>`;
        
        if (isValid) {
            html += `<div class="test-result-item success">`;
            html += `✅ <strong>Validazione riuscita!</strong> L'età corrisponde alla categoria selezionata.`;
            html += `</div>`;
        } else {
            html += `<div class="test-result-item error">`;
            html += `❌ <strong>Validazione fallita!</strong> L'età non corrisponde alla categoria selezionata.`;
            html += `</div>`;
            
            if (correctCategory) {
                html += `<div class="test-result-item warning">`;
                html += `💡 <strong>Suggerimento:</strong> La categoria corretta sarebbe "${correctCategory.label}".`;
                html += `</div>`;
            } else {
                html += `<div class="test-result-item warning">`;
                html += `⚠️ <strong>Attenzione:</strong> Nessuna categoria disponibile per questa età.`;
                html += `</div>`;
            }
        }
        
        $results.html(html).show();
    }
});
</script>

<?php