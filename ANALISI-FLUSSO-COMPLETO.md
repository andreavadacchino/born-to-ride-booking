# Analisi Flusso Completo - Born to Ride Booking

## Executive Summary

Il sistema gestisce tre componenti principali che presentano alcune criticità nel loro flusso di dati:

1. **Extra Costs (Costi Extra)**: Sistema a due livelli (per persona e per durata) con possibili duplicazioni
2. **Notti Extra**: Calcolo supplementi con numero di notti non sempre correttamente propagato
3. **Etichette Bambini Dinamiche**: Sistema dinamico non sempre sincronizzato tra frontend e backend

## 1. EXTRA COSTS (COSTI EXTRA)

### 1.1 Flusso Dati

#### Frontend (frontend-scripts.js)
```javascript
// Raccolta dati nel form anagrafici
const costi_extra = {};
$(this).find('input[type="checkbox"]:checked').each(function () {
    const match = name.match(/anagrafici\[\d+\]\[costi_extra\]\[([^\]]+)\]/);
    if (match && match[1]) {
        costi_extra[match[1]] = true;
    }
});
```

#### Backend - Salvataggio (class-btr-preventivi.php)
```php
// Processo costi extra per persona
foreach ($persona['costi_extra'] as $cost_key => $is_selected) {
    if ($is_selected && isset($persona['costi_extra_dettagliate'][$cost_key])) {
        $dettaglio = $persona['costi_extra_dettagliate'][$cost_key];
        // Calcolo importo con moltiplicatori
        $importo_finale = $importo_base;
        if ($moltiplica_durata && $durata_giorni > 0) {
            $importo_finale *= intval($durata_giorni);
        }
    }
}
```

#### Visualizzazione (preventivo-review.php, preventivo-summary.php)
- I template mostrano sia costi per persona che per durata
- Utilizzano la funzione `btr_aggregate_extra_costs()` per aggregare i dati

### 1.2 Problemi Identificati

1. **Doppio Conteggio**: 
   - I costi possono essere salvati sia in `_anagrafici_preventivo` che in `_costi_extra_durata`
   - La funzione `btr_aggregate_extra_costs()` processa entrambi senza controlli di duplicazione

2. **Inconsistenza Dati**:
   - Il frontend salva solo flag booleani (`true/false`)
   - Il backend si aspetta strutture dettagliate con importi e moltiplicatori

3. **Serializzazione Multiple**:
   - I dati vengono serializzati/deserializzati multiple volte nel flusso
   - Possibili perdite di dati durante le conversioni

### 1.3 Hook WordPress Utilizzati

- `wp_ajax_btr_save_preventivo`: Salvataggio AJAX del preventivo
- `woocommerce_cart_loaded_from_session`: Inserimento automatico costi extra nel carrello
- `woocommerce_before_calculate_totals`: Aggiustamento prezzi nel carrello

## 2. NOTTI EXTRA

### 2.1 Flusso Dati

#### Frontend
```javascript
// Gestione numero notti extra
if (extraNightsActive) {
    if (typeof response.data.extra_nights_count !== 'undefined') {
        window.btrExtraNightsCount = parseInt(response.data.extra_nights_count, 10) || 0;
    }
}
```

#### Backend - Calcolo (class-btr-preventivi.php)
```php
// Calcolo prezzi notti extra
if (!empty($extra_night_flag) && $extra_night_pp > 0 && $numero_notti_extra > 0) {
    // Calcolo per adulti
    $row_extra_night_costs += $extra_night_pp * $adulti_in_camera * $numero_notti_extra;
    
    // Calcolo per bambini con prezzi differenziati
    if ($assigned_child_f1 > 0) {
        $child_f1_price_per_night = 22.00; // Hardcoded!
        $child_f1_total = $child_f1_price_per_night * $numero_notti_extra;
        $row_extra_night_costs += $child_f1_total * $assigned_child_f1;
    }
}
```

### 2.2 Problemi Identificati

1. **Prezzi Hardcoded**:
   - I prezzi per bambini sono hardcoded nel codice (€22, €23, etc.)
   - Non utilizzano il sistema di pricing dinamico

2. **Numero Notti Non Sincronizzato**:
   - Il numero di notti extra non sempre viene passato dal frontend
   - Fallback a valore default (2) può causare calcoli errati

3. **Calcolo Supplementi Duplicato**:
   - I supplementi vengono calcolati sia per il periodo base che per le notti extra
   - Possibile doppio conteggio in alcuni scenari

### 2.3 Metadati Salvati

- `_numero_notti_extra`: Numero di notti aggiuntive
- `_extra_night_flag`: Flag attivazione notti extra
- `_extra_night_pp`: Prezzo per persona per notte
- `_extra_night_total`: Totale calcolato notti extra

## 3. ETICHETTE BAMBINI DINAMICHE

### 3.1 Sistema di Gestione

#### Classe Principale (class-btr-dynamic-child-categories.php)
```php
const DEFAULT_CATEGORIES = [
    [
        'id' => 'f1',
        'label' => 'Bambini 3-8 anni',
        'age_min' => 3,
        'age_max' => 8,
        'discount_type' => 'percentage',
        'discount_value' => 50
    ],
    // ...altre categorie
];
```

#### Frontend - Utilizzo Etichette
```javascript
// Funzione per ottenere etichette dinamiche
const getChildLabel = (fasciaId, fallback) => {
    if (window.btrChildFasce && Array.isArray(window.btrChildFasce)) {
        const fascia = window.btrChildFasce.find(f => f.id === fasciaId);
        return fascia ? fascia.label : fallback;
    }
    return fallback;
};
```

### 3.2 Problemi Identificati

1. **Sincronizzazione Frontend/Backend**:
   - Le etichette vengono generate dinamicamente ma non sempre salvate nel preventivo
   - Il frontend può mostrare etichette diverse da quelle salvate

2. **Fallback Inconsistenti**:
   - Diversi fallback utilizzati in punti diversi del codice
   - Possibili inconsistenze nelle etichette mostrate

3. **Propagazione nel Checkout**:
   - Le etichette dinamiche non sempre vengono propagate correttamente al checkout WooCommerce
   - Il sistema usa sia ID numerici che stringhe ('f1', 'f2', etc.)

### 3.3 Hook e Filtri

- `wp_enqueue_scripts`: Caricamento configurazioni frontend
- `woocommerce_store_api_cart_item_response`: Aggiunta etichette al checkout React
- `admin_menu`: Pagina admin per configurazione categorie

## 4. PUNTI CRITICI E RACCOMANDAZIONI

### 4.1 Punti Critici nel Flusso

1. **Conversione Preventivo → Carrello** (`class-btr-preventivi-ordini.php`):
   - Momento critico dove i dati vengono trasformati
   - Possibili perdite di informazioni durante la conversione
   - Duplicazione di logica di calcolo

2. **Hook `woocommerce_before_calculate_totals`**:
   - Eseguito multiple volte
   - Può sovrascrivere prezzi calcolati correttamente
   - Necessita di meccanismo di cache per evitare ricalcoli

3. **Salvataggio Metadati**:
   - Troppi punti di salvataggio diversi
   - Possibile inconsistenza tra diversi metadati
   - Mancanza di validazione centralizzata

### 4.2 Raccomandazioni Immediate

1. **Centralizzare Calcoli**:
   ```php
   class BTR_Price_Calculator {
       public static function calculate_total($preventivo_id) {
           // Logica centralizzata di calcolo
       }
   }
   ```

2. **Validazione Dati**:
   ```php
   class BTR_Data_Validator {
       public static function validate_extra_costs($data) {
           // Validazione struttura dati
       }
   }
   ```

3. **Cache Calcoli**:
   ```php
   // In adjust_cart_item_prices
   static $calculated_prices = [];
   $cache_key = md5(serialize($cart_item));
   if (isset($calculated_prices[$cache_key])) {
       return $calculated_prices[$cache_key];
   }
   ```

4. **Sincronizzazione Etichette**:
   - Salvare sempre le etichette utilizzate nel preventivo
   - Utilizzare quelle salvate invece di rigenerarle
   - Implementare sistema di versioning per le configurazioni

### 4.3 Bug Fixes Prioritari

1. **Extra Costs Doppio Conteggio**:
   - Implementare controllo unicità in `btr_aggregate_extra_costs()`
   - Utilizzare hash o ID univoci per evitare duplicazioni

2. **Notti Extra Hardcoded**:
   - Sostituire prezzi hardcoded con chiamate al sistema di pricing
   - Sincronizzare numero notti tra frontend e backend

3. **Etichette Dinamiche**:
   - Salvare snapshot delle etichette al momento della creazione preventivo
   - Utilizzare sempre le etichette salvate nel flusso successivo

## 5. SCHEMA FLUSSO DATI

```
FRONTEND (Selezione)
    ↓
AJAX (Salvataggio Preventivo)
    ↓
DATABASE (Meta Preventivo)
    ↓
CONVERSIONE (Preventivo → Carrello)
    ↓
WOOCOMMERCE (Calcolo Totali)
    ↓
CHECKOUT (Visualizzazione)
    ↓
ORDINE (Salvataggio Finale)
```

### Punti di Intervento:
- **A**: Validazione dati in ingresso (AJAX)
- **B**: Normalizzazione struttura dati (Database)
- **C**: Mappatura consistente (Conversione)
- **D**: Cache calcoli (WooCommerce)
- **E**: Verifica totali (Checkout)

## 6. TESTING CONSIGLIATO

1. **Test Case Extra Costs**:
   - Selezionare solo costi per persona
   - Selezionare solo costi per durata
   - Selezionare mix di entrambi
   - Verificare totali in ogni step

2. **Test Case Notti Extra**:
   - 0 notti extra
   - 1 notte extra
   - Multiple notti extra
   - Mix adulti/bambini

3. **Test Case Etichette**:
   - Modificare etichette in admin
   - Creare nuovo preventivo
   - Verificare consistenza fino all'ordine

## CONCLUSIONI

Il sistema presenta una complessità significativa con multiple dipendenze tra componenti. I principali problemi derivano da:

1. Mancanza di un singolo source of truth per i dati
2. Duplicazione di logica di calcolo in punti diversi
3. Conversioni multiple dei dati che causano perdite/inconsistenze
4. Mancanza di validazione centralizzata

La soluzione richiede un refactoring progressivo partendo dai punti più critici (calcolo prezzi e gestione extra costs) per poi estendersi agli altri componenti.