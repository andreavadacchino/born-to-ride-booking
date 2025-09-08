<?php
/**
 * Test visualizzazione dati anagrafici v1.0.157
 * Verifica che i dati salvati nel DB vengano visualizzati correttamente
 */

// Security check
if (!defined('ABSPATH')) {
    require_once '../../../../wp-load.php';
}

// Solo admin può eseguire questo test
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

// ID del preventivo di test
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 36765;

echo '<h1>Test Visualizzazione Dati Anagrafici - v1.0.157</h1>';
echo '<p>Preventivo ID: ' . $preventivo_id . '</p>';

// 1. VERIFICA DATI SALVATI NEL DB
echo '<h2>1. Dati Salvati nel Database</h2>';

// Totali salvati con chiavi _pricing_*
$pricing_fields = [
    '_pricing_totale_generale' => 'Totale Generale',
    '_pricing_totale_camere' => 'Totale Camere',
    '_pricing_totale_costi_extra' => 'Totale Costi Extra',
    '_pricing_totale_assicurazioni' => 'Totale Assicurazioni',
    '_pricing_subtotale_prezzi_base' => 'Subtotale Prezzi Base',
    '_pricing_subtotale_supplementi_base' => 'Subtotale Supplementi Base'
];

echo '<h3>Totali Pricing:</h3>';
echo '<table border="1" cellpadding="5">';
echo '<tr><th>Campo</th><th>Valore Salvato</th><th>Status</th></tr>';
foreach ($pricing_fields as $key => $label) {
    $value = get_post_meta($preventivo_id, $key, true);
    $status = !empty($value) ? '✅' : '❌';
    echo '<tr>';
    echo '<td>' . $label . '</td>';
    echo '<td>' . (!empty($value) ? '€ ' . number_format(floatval($value), 2, ',', '.') : 'VUOTO') . '</td>';
    echo '<td>' . $status . '</td>';
    echo '</tr>';
}
echo '</table>';

// Anagrafici salvati con chiavi _anagrafico_X_*
echo '<h3>Anagrafici Individuali:</h3>';
$anagrafici_count = intval(get_post_meta($preventivo_id, '_anagrafici_count', true));
echo '<p>Numero anagrafici salvati: ' . $anagrafici_count . '</p>';

if ($anagrafici_count > 0) {
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>#</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Costi Extra</th></tr>';
    
    for ($i = 0; $i < $anagrafici_count; $i++) {
        $nome = get_post_meta($preventivo_id, "_anagrafico_{$i}_nome", true);
        $cognome = get_post_meta($preventivo_id, "_anagrafico_{$i}_cognome", true);
        $email = get_post_meta($preventivo_id, "_anagrafico_{$i}_email", true);
        
        $extras = [];
        if (get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_no_skipass_selected", true)) {
            $price = get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_no_skipass_price", true);
            $extras[] = "No Skipass (€$price)";
        }
        if (get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_culla_per_neonati_selected", true)) {
            $price = get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_culla_per_neonati_price", true);
            $extras[] = "Culla (€$price)";
        }
        
        echo '<tr>';
        echo '<td>' . ($i + 1) . '</td>';
        echo '<td>' . esc_html($nome) . '</td>';
        echo '<td>' . esc_html($cognome) . '</td>';
        echo '<td>' . esc_html($email) . '</td>';
        echo '<td>' . (!empty($extras) ? implode(', ', $extras) : '-') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// 2. VERIFICA CARICAMENTO NEL TEMPLATE
echo '<h2>2. Test Caricamento nel Template</h2>';

// Simula il caricamento come nel template
$anagrafici_loaded = [];
if ($anagrafici_count > 0) {
    for ($i = 0; $i < $anagrafici_count; $i++) {
        $nome = get_post_meta($preventivo_id, "_anagrafico_{$i}_nome", true);
        if (!empty($nome)) {
            $anagrafico = [
                'nome' => $nome,
                'cognome' => get_post_meta($preventivo_id, "_anagrafico_{$i}_cognome", true),
                'email' => get_post_meta($preventivo_id, "_anagrafico_{$i}_email", true),
                'telefono' => get_post_meta($preventivo_id, "_anagrafico_{$i}_telefono", true),
                'costi_extra' => []
            ];
            
            $extra_skipass = get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_no_skipass_selected", true);
            if ($extra_skipass) {
                $anagrafico['costi_extra']['no-skipass'] = [
                    'selected' => '1',
                    'price' => get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_no_skipass_price", true) ?: '-35'
                ];
            }
            
            $extra_culla = get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_culla_per_neonati_selected", true);
            if ($extra_culla) {
                $anagrafico['costi_extra']['culla-per-neonati'] = [
                    'selected' => '1',
                    'price' => get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_culla_per_neonati_price", true) ?: '15'
                ];
            }
            
            $anagrafici_loaded[] = $anagrafico;
        }
    }
}

echo '<p>Anagrafici caricati con successo: ' . count($anagrafici_loaded) . '</p>';
if (!empty($anagrafici_loaded)) {
    echo '<pre>';
    print_r($anagrafici_loaded);
    echo '</pre>';
}

// 3. CONFRONTO CON JSON COMPLETO
echo '<h2>3. Confronto con JSON Completo</h2>';
$json_completo = get_post_meta($preventivo_id, '_btr_dati_completi_json', true);
if ($json_completo) {
    $decoded = json_decode($json_completo, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo '<p>✅ JSON decodificato correttamente</p>';
        
        // Estrai anagrafici dal JSON
        $anagrafici_json = null;
        if (isset($decoded['anagrafici'])) {
            $anagrafici_json = $decoded['anagrafici'];
        } elseif (isset($decoded['booking_data_json']['anagrafici'])) {
            $anagrafici_json = $decoded['booking_data_json']['anagrafici'];
        }
        
        if ($anagrafici_json) {
            echo '<p>Anagrafici nel JSON: ' . count($anagrafici_json) . '</p>';
            echo '<p>Anagrafici nei meta individuali: ' . count($anagrafici_loaded) . '</p>';
            
            if (count($anagrafici_json) === count($anagrafici_loaded)) {
                echo '<p style="color: green;">✅ Il numero di anagrafici corrisponde!</p>';
            } else {
                echo '<p style="color: red;">❌ ATTENZIONE: Il numero di anagrafici NON corrisponde!</p>';
            }
        }
        
        // Mostra totali dal JSON
        if (isset($decoded['prezzi'])) {
            echo '<h3>Totali dal JSON:</h3>';
            echo '<ul>';
            echo '<li>Totale generale: €' . number_format($decoded['prezzi']['totale_generale'] ?? 0, 2, ',', '.') . '</li>';
            echo '<li>Totale camere: €' . number_format($decoded['prezzi']['totale_camere'] ?? 0, 2, ',', '.') . '</li>';
            echo '<li>Totale costi extra: €' . number_format($decoded['prezzi']['totale_costi_extra'] ?? 0, 2, ',', '.') . '</li>';
            echo '</ul>';
        }
    } else {
        echo '<p>❌ Errore decodifica JSON: ' . json_last_error_msg() . '</p>';
    }
}

// 4. RIEPILOGO FINALE
echo '<h2>4. Riepilogo Finale v1.0.157</h2>';
echo '<div style="background: #f0f0f0; padding: 20px; border: 2px solid #333;">';
echo '<h3>Dati che DOVREBBERO essere visualizzati:</h3>';

$totale_generale = floatval(get_post_meta($preventivo_id, '_pricing_totale_generale', true));
$totale_camere = floatval(get_post_meta($preventivo_id, '_pricing_totale_camere', true));
$totale_costi_extra = floatval(get_post_meta($preventivo_id, '_pricing_totale_costi_extra', true));

echo '<table style="width: 100%;">';
echo '<tr><td><strong>Totale Camere:</strong></td><td style="text-align: right;">€ ' . number_format($totale_camere, 2, ',', '.') . '</td></tr>';
echo '<tr><td><strong>Costi Extra:</strong></td><td style="text-align: right;">€ ' . number_format($totale_costi_extra, 2, ',', '.') . '</td></tr>';
echo '<tr style="border-top: 2px solid #000;"><td><strong>TOTALE FINALE:</strong></td><td style="text-align: right;"><strong>€ ' . number_format($totale_generale, 2, ',', '.') . '</strong></td></tr>';
echo '</table>';

echo '<h3>Partecipanti (' . count($anagrafici_loaded) . '):</h3>';
echo '<ol>';
foreach ($anagrafici_loaded as $p) {
    $extras = [];
    foreach ($p['costi_extra'] ?? [] as $slug => $data) {
        if (!empty($data['selected'])) {
            $extras[] = str_replace('-', ' ', $slug) . ' (€' . $data['price'] . ')';
        }
    }
    echo '<li>' . $p['nome'] . ' ' . $p['cognome'];
    if (!empty($extras)) {
        echo ' - Extra: ' . implode(', ', $extras);
    }
    echo '</li>';
}
echo '</ol>';

echo '</div>';

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=btr-test-runner') . '">← Torna ai test</a></p>';
?>