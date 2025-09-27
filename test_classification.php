<?php
// Test script per analizzare classificazione partecipanti
// Dati estratti dal database per preventivo 37512

$anagrafici_data = 'a:4:{i:0;a:22:{s:4:"nome";s:6:"Andrea";s:7:"cognome";s:10:"Vadacchino";s:12:"data_nascita";s:10:"2025-09-03";s:5:"email";s:26:"andreavadacchino@gmail.com";s:8:"telefono";s:15:"+39 333 1721964";s:13:"citta_nascita";s:6:"Torino";s:15:"citta_residenza";s:6:"Torino";s:19:"provincia_residenza";s:9:"Agrigento";s:19:"indirizzo_residenza";s:18:"Via Rodolfo Renier";s:13:"numero_civico";s:2:"37";s:13:"cap_residenza";s:5:"10141";s:14:"codice_fiscale";s:16:"VDCNDR85A15G317V";s:6:"camera";s:3:"0-2";s:11:"camera_tipo";s:19:"Doppia/Matrimoniale";s:10:"tipo_letto";s:13:"letti_singoli";s:12:"tipo_persona";s:6:"adulto";s:10:"rc_skipass";s:1:"0";s:16:"ass_annullamento";s:1:"0";s:12:"ass_bagaglio";s:1:"0";s:6:"fascia";s:0:"";s:13:"assicurazioni";a:1:{s:24:"assicurazione-rc-skipass";s:1:"1";}s:25:"assicurazioni_dettagliate";a:1:{s:24:"assicurazione-rc-skipass";a:4:{s:2:"id";i:0;s:11:"descrizione";s:24:"Assicurazione RC Skipass";s:7:"importo";d:5;s:11:"percentuale";d:0;}}}i:1;a:22:{s:4:"nome";s:5:"Moira";s:7:"cognome";s:6:"Vetere";s:12:"data_nascita";s:10:"2025-09-04";s:5:"email";s:26:"andreavadacchino@gmail.com";s:8:"telefono";s:15:"+39 331 7196438";s:13:"citta_nascita";s:20:"CAMPORA SAN GIOVANNI";s:15:"citta_residenza";s:20:"CAMPORA SAN GIOVANNI";s:19:"provincia_residenza";s:11:"Alessandria";s:19:"indirizzo_residenza";s:0:"";s:13:"numero_civico";s:0:"";s:13:"cap_residenza";s:0:"";s:14:"codice_fiscale";s:0:"";s:6:"camera";s:3:"0-1";s:11:"camera_tipo";s:19:"Doppia/Matrimoniale";s:10:"tipo_letto";s:12:"matrimoniale";s:12:"tipo_persona";s:6:"adulto";s:10:"rc_skipass";s:1:"0";s:16:"ass_annullamento";s:1:"0";s:12:"ass_bagaglio";s:1:"0";s:6:"fascia";s:0:"";s:13:"assicurazioni";a:1:{s:24:"assicurazione-rc-skipass";s:1:"1";}s:25:"assicurazioni_dettagliate";a:1:{s:24:"assicurazione-rc-skipass";a:4:{s:2:"id";i:0;s:11:"descrizione";s:24:"Assicurazione RC Skipass";s:7:"importo";d:5;s:11:"percentuale";d:0;}}}i:2;a:22:{s:4:"nome";s:2:"De";s:7:"cognome";s:7:"Daniele";s:12:"data_nascita";s:10:"2025-09-11";s:5:"email";s:0:"";s:8:"telefono";s:0:"";s:13:"citta_nascita";s:6:"Torino";s:15:"citta_residenza";s:6:"Torino";s:19:"provincia_residenza";s:9:"Agrigento";s:19:"indirizzo_residenza";s:0:"";s:13:"numero_civico";s:0:"";s:13:"cap_residenza";s:0:"";s:14:"codice_fiscale";s:0:"";s:6:"camera";s:3:"0-2";s:11:"camera_tipo";s:19:"Doppia/Matrimoniale";s:10:"tipo_letto";s:13:"letti_singoli";s:12:"tipo_persona";s:7:"bambino";s:10:"rc_skipass";s:1:"0";s:16:"ass_annullamento";s:1:"0";s:12:"ass_bagaglio";s:1:"0";s:6:"fascia";s:2:"f1";s:13:"assicurazioni";a:1:{s:24:"assicurazione-rc-skipass";s:1:"1";}s:25:"assicurazioni_dettagliate";a:1:{s:24:"assicurazione-rc-skipass";a:4:{s:2:"id";i:0;s:11:"descrizione";s:24:"Assicurazione RC Skipass";s:7:"importo";d:5;s:11:"percentuale";d:0;}}}i:3;a:20:{s:4:"nome";s:8:"Leonardo";s:7:"cognome";s:9:"Colatorti";s:12:"data_nascita";s:0:"";s:5:"email";s:0:"";s:8:"telefono";s:0:"";s:13:"citta_nascita";s:0:"";s:15:"citta_residenza";s:0:"";s:19:"provincia_residenza";s:0:"";s:19:"indirizzo_residenza";s:0:"";s:13:"numero_civico";s:0:"";s:13:"cap_residenza";s:0:"";s:14:"codice_fiscale";s:0:"";s:6:"camera";s:3:"0-1";s:11:"camera_tipo";s:19:"Doppia/Matrimoniale";s:10:"tipo_letto";s:12:"matrimoniale";s:12:"tipo_persona";s:7:"neonato";s:10:"rc_skipass";s:1:"0";s:16:"ass_annullamento";s:1:"0";s:12:"ass_bagaglio";s:1:"0";s:6:"fascia";s:7:"neonato";}}';

$anagrafici = unserialize($anagrafici_data);

echo "=== TEST CLASSIFICAZIONE PARTECIPANTI ===\n\n";
echo "Totale partecipanti: " . count($anagrafici) . "\n\n";

$adulti_paganti = [];
$bambini_neonati = [];

foreach ($anagrafici as $index => $persona) {
    // Logica identica al template
    $tipo = strtolower(trim($persona['tipo_persona'] ?? ''));
    $fascia = strtolower(trim($persona['fascia'] ?? ''));
    $is_adult = ($tipo === 'adulto') || ($fascia === 'adulto');
    
    // Test calcolo età
    if (!$is_adult && !empty($persona['data_nascita'])) {
        try { 
            $age = (new DateTime())->diff(new DateTime($persona['data_nascita']))->y; 
            $is_adult_by_age = ($age >= 18);
            $is_adult = $is_adult_by_age;
            echo ">>> Calcolo età per index $index: data={$persona['data_nascita']}, età=$age, adulto_per_età=" . ($is_adult_by_age ? 'SI' : 'NO') . "\n";
        } catch (Exception $e) {
            echo ">>> ERRORE calcolo età per index $index: {$e->getMessage()}\n";
        }
    }
    
    $label = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
    
    echo "Index $index - {$label}:\n";
    echo "  tipo_persona: '{$tipo}'\n";
    echo "  fascia: '{$fascia}'\n";
    echo "  data_nascita: '{$persona['data_nascita']}'\n";
    echo "  is_adult (finale): " . ($is_adult ? 'TRUE' : 'FALSE') . "\n";
    echo "  label: '{$label}'\n";
    echo "  Condizione (\$is_adult && \$label): " . (($is_adult && $label) ? 'PASSA - ADULTO PAGANTE' : 'NON PASSA - BAMBINO/NEONATO') . "\n";
    
    if ($is_adult && $label) {
        $adulti_paganti[] = [
            'index' => $index,
            'nome' => $label,
            'email' => $persona['email'] ?? '',
        ];
        echo "  >>> ✅ AGGIUNTO A ADULTI_PAGANTI\n";
    } else {
        $bambini_neonati[] = [
            'index' => $index,
            'label' => $label ?: ('Persona #'.($index+1)),
            'fascia' => $fascia,
        ];
        echo "  >>> ❌ AGGIUNTO A BAMBINI_NEONATI\n";
    }
    echo "\n";
}

echo "=== RISULTATO FINALE ===\n";
echo "Adulti paganti: " . count($adulti_paganti) . "\n";
foreach ($adulti_paganti as $idx => $adult) {
    echo "  - [$idx] Index: {$adult['index']}, Nome: {$adult['nome']}\n";
}

echo "\nBambini/Neonati: " . count($bambini_neonati) . "\n";
foreach ($bambini_neonati as $idx => $child) {
    echo "  - [$idx] Index: {$child['index']}, Label: {$child['label']}, Fascia: {$child['fascia']}\n";
}

echo "\nDovrebbero essere generate " . count($adulti_paganti) . " checkbox\n";
?>