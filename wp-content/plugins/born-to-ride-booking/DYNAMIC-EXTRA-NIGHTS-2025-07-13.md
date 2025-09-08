# Implementazione Notti Extra Dinamiche - 2025-01-12

## Panoramica
Implementato il recupero dinamico del numero di notti extra configurate nel backend, sostituendo il valore hardcoded di "2 notti extra" con il numero effettivo configurato per ogni data specifica.

## Problema Risolto
Prima dell'implementazione:
- Il sistema mostrava sempre "2 Notti extra" indipendentemente dalla configurazione
- Il numero era hardcoded in `frontend-scripts.js`
- Non rispettava le configurazioni admin in `gestione-allotment-camere.php`

## Soluzione Implementata

### 1. Backend - Conteggio Notti Extra
**File:** `includes/class-btr-shortcodes.php` (righe 3347-3371)

```php
// Calcola dinamicamente il numero di notti extra disponibili per la data selezionata
$extra_nights_count = 0;
if (!empty($selected_date) && !empty($camere_extra_allotment_by_date)) {
    // Normalizza la data selezionata per il confronto
    $norm_selected = $selected_start_yMd ?: date('Y-m-d', strtotime($this->convert_italian_date_to_english($selected_date)));
    
    foreach ($camere_extra_allotment_by_date as $date_key => $config) {
        // Normalizza la chiave data
        $norm_key = btr_parse_range_start_yMd($date_key);
        if (!$norm_key) {
            $norm_key = date('Y-m-d', strtotime($this->convert_italian_date_to_english($date_key)));
        }
        
        // Se troviamo la data corrispondente
        if ($norm_key === $norm_selected) {
            // Conta le date nel campo range
            if (isset($config['range']) && is_array($config['range'])) {
                $extra_nights_count = count($config['range']);
                error_log("[BTR] Notti extra per $selected_date: " . $extra_nights_count . " notti");
                error_log("[BTR] Date notti extra: " . implode(', ', $config['range']));
            }
            break;
        }
    }
}
```

Il valore viene aggiunto alla risposta AJAX:
```php
'extra_nights_count' => $extra_nights_count, // Numero dinamico di notti extra disponibili
```

### 2. Frontend - Utilizzo del Valore Dinamico
**File:** `assets/js/frontend-scripts.js`

#### Salvataggio del valore (righe 1391-1395):
```javascript
// Salva il numero di notti extra ricevuto dal backend
if (response.success && response.data && typeof response.data.extra_nights_count !== 'undefined') {
    window.btrExtraNightsCount = parseInt(response.data.extra_nights_count, 10) || 0;
    console.log('[BTR] 📅 Numero notti extra dal backend:', window.btrExtraNightsCount);
}
```

#### Utilizzo nei calcoli (riga 2215):
```javascript
// Usa il numero dinamico di notti extra dal backend, con fallback a 2
extraNightDays = window.btrExtraNightsCount || 2;
```

#### Visualizzazione nel breakdown (esempio righe 2516-2517):
```javascript
const extraNightsCount = window.btrExtraNightsCount || 2;
let extraLine = `<strong>${extraNightsCount} Nott${extraNightsCount === 1 ? 'e' : 'i'} extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>${btrFormatPrice(extraNightPP)}</strong> a persona`;
```

## File Modificati
1. `/includes/class-btr-shortcodes.php` - Aggiunto calcolo e output notti extra
2. `/assets/js/frontend-scripts.js` - Aggiornato per utilizzare valore dinamico

## Test e Verifica
Creato file di test: `tests/test-dynamic-extra-nights.php`
- Visualizza le configurazioni notti extra per ogni data
- Mostra il conteggio per ogni configurazione
- Fornisce istruzioni per la verifica frontend

## Comportamento
1. L'admin configura le notti extra in `gestione-allotment-camere.php`
2. Può selezionare fino a 3 giorni prima/dopo il pacchetto
3. Il sistema conta automaticamente quante date sono state selezionate
4. Il frontend mostra "1 Notte extra", "2 Notti extra", "3 Notti extra" ecc. in base alla configurazione
5. Se non ci sono configurazioni, usa il fallback di 2 notti (comportamento legacy)

## Note Tecniche
- Il conteggio si basa sull'array `range` nel campo `btr_camere_extra_allotment_by_date`
- La normalizzazione delle date gestisce sia range che date singole
- Il valore è salvato in `window.btrExtraNightsCount` per uso globale nel frontend
- Supporta singolare/plurale italiano ("Notte"/"Notti")

## Retrocompatibilità
- Mantiene fallback a 2 notti se non ci sono configurazioni
- Non richiede modifiche al database
- Compatibile con configurazioni esistenti