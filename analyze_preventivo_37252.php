<?php
/**
 * Analisi completa preventivo ID 37252
 * Identifica la discrepanza tra valori database e valori richiesti
 */

// Connessione database
$socket = '/Users/andreavadacchino/Library/Application Support/Local/run/hFKO0EI1f/mysql/mysqld.sock';
$mysqli = new mysqli('localhost', 'root', 'root', 'local', null, $socket);

if ($mysqli->connect_error) {
    die("Connessione fallita: " . $mysqli->connect_error);
}

echo "=== ANALISI PREVENTIVO ID 37252 ===\n\n";

// 1. Recupera TUTTI i meta del preventivo
$query = "SELECT meta_key, meta_value FROM KrUHSqSf_postmeta WHERE post_id = 37252 ORDER BY meta_key";
$result = $mysqli->query($query);

$meta_data = [];
echo "TUTTI I META POST DEL PREVENTIVO 37252:\n";
echo "=====================================\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $meta_data[$row['meta_key']] = $row['meta_value'];
        
        // Evidenzia i meta relativi ad assicurazioni e costi extra
        $highlight = '';
        if (stripos($row['meta_key'], 'assicuraz') !== false || 
            stripos($row['meta_key'], 'extra') !== false ||
            stripos($row['meta_key'], 'totale') !== false ||
            stripos($row['meta_key'], 'pricing') !== false) {
            $highlight = ' *** RILEVANTE ***';
        }
        
        echo sprintf("%-40s = %s%s\n", $row['meta_key'], $row['meta_value'], $highlight);
    }
} else {
    echo "Nessun meta trovato per il preventivo 37252\n";
}

echo "\n\n=== ANALISI VALORI SPECIFICI ===\n";
echo "=================================\n";

// 2. Analizza i valori problematici
$problematic_keys = [
    '_btr_totale_assicurazioni',
    '_totale_costi_extra',
    '_btr_payload_pricing_insurance_total',
    '_btr_payload_pricing_extra_costs_total',
    '_btr_payload'
];

foreach ($problematic_keys as $key) {
    if (isset($meta_data[$key])) {
        echo "TROVATO $key: " . $meta_data[$key] . "\n";
        
        // Se è JSON, decodifica
        if (is_string($meta_data[$key]) && (strpos($meta_data[$key], '{') === 0 || strpos($meta_data[$key], '[') === 0)) {
            $decoded = json_decode($meta_data[$key], true);
            if ($decoded !== null) {
                echo "  -> Decodificato JSON:\n";
                print_r($decoded);
            }
        }
    } else {
        echo "NON TROVATO: $key\n";
    }
}

echo "\n\n=== RICERCA VALORI €15 ===\n";
echo "==========================\n";

// 3. Cerca tutti i meta che contengono il valore 15
$found_15_values = [];
foreach ($meta_data as $key => $value) {
    if (strval($value) === '15' || strval($value) === '15.00') {
        $found_15_values[$key] = $value;
        echo "VALORE 15 TROVATO in: $key = $value\n";
    }
    
    // Cerca anche nei JSON
    if (is_string($value) && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
        $decoded = json_decode($value, true);
        if ($decoded !== null) {
            $json_str = json_encode($decoded);
            if (strpos($json_str, '"15"') !== false || strpos($json_str, ':15') !== false) {
                echo "VALORE 15 TROVATO in JSON di $key:\n";
                print_r($decoded);
            }
        }
    }
}

echo "\n\n=== ANALISI PAYLOAD PRINCIPALE ===\n";
echo "===================================\n";

// 4. Analizza il payload principale se esiste
if (isset($meta_data['_btr_payload'])) {
    $payload = json_decode($meta_data['_btr_payload'], true);
    if ($payload !== null) {
        echo "PAYLOAD DECODIFICATO:\n";
        
        // Cerca sezioni rilevanti
        if (isset($payload['pricing'])) {
            echo "\nSEZIONE PRICING:\n";
            foreach ($payload['pricing'] as $pricing_key => $pricing_value) {
                if (stripos($pricing_key, 'insurance') !== false || 
                    stripos($pricing_key, 'assicuraz') !== false ||
                    stripos($pricing_key, 'extra') !== false) {
                    echo "  $pricing_key = $pricing_value\n";
                }
            }
        }
        
        // Cerca valori 15 nel payload
        function search_value_in_array($array, $search_value, $path = '') {
            foreach ($array as $key => $value) {
                $current_path = $path ? "$path.$key" : $key;
                
                if (is_array($value)) {
                    search_value_in_array($value, $search_value, $current_path);
                } elseif (strval($value) === strval($search_value)) {
                    echo "VALORE $search_value trovato in payload: $current_path = $value\n";
                }
            }
        }
        
        echo "\nRICERCA VALORE 15 NEL PAYLOAD:\n";
        search_value_in_array($payload, 15);
        search_value_in_array($payload, '15');
        
    } else {
        echo "Payload non è un JSON valido\n";
    }
}

echo "\n\n=== RIEPILOGO DISCREPANZE ===\n";
echo "==============================\n";
echo "Database mostra:\n";
echo "- _btr_totale_assicurazioni: " . ($meta_data['_btr_totale_assicurazioni'] ?? 'NON TROVATO') . "\n";
echo "- _totale_costi_extra: " . ($meta_data['_totale_costi_extra'] ?? 'NON TROVATO') . "\n";
echo "\nUtente vuole mostrare:\n";
echo "- Totale assicurazioni: €15\n";
echo "- Totale costi extra: €15\n";

echo "\n\n=== SUGGERIMENTI ===\n";
echo "=====================\n";
echo "1. Controllare se esistono altri meta con valore 15\n";
echo "2. Verificare calcoli nel payload JSON\n";
echo "3. Analizzare logica di trasformazione nel codice PHP\n";

$mysqli->close();
?>