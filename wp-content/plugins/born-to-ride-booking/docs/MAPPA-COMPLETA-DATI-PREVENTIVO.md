# 📋 MAPPA COMPLETA DATI PREVENTIVO - Born to Ride

**Versione**: v1.0.189  
**Data**: 17 Gennaio 2025  
**Scopo**: Riferimento completo per accesso ai dati del preventivo

---

## 🎯 GUIDA RAPIDA - QUALE CAMPO USARE

| **Dato Richiesto** | **Campo Meta da Usare** | **Formato** | **Note** |
|---|---|---|---|
| **Dati Cliente** | `_btr_cliente_nome`, `_btr_cliente_email` | string | ✅ Sempre aggiornati |
| **Camere con Assegnazioni** | `_btr_booking_data_json['rooms']` | array | ✅ **USARE QUESTO** |
| **Totali Corretti** | `_pricing_*` con prefisso | float | ✅ Sempre aggiornati |
| **Anagrafici Completi** | `_btr_anagrafici` | array | ✅ Dati completi |
| **Breakdown Calcoli** | `_riepilogo_calcoli_dettagliato` | array | ✅ Per dettagli |
| **Costi Extra** | `_anagrafico_X_extra_*` | vari | ✅ Per persona |

### ❌ CAMPI DA NON USARE (OBSOLETI)
- `_btr_camere_selezionate` → Dati a 0, usare `_booking_data_json['rooms']`
- `_camere_selezionate` → Legacy, incompleto
- Totali senza prefisso → Possono essere obsoleti

---

## 📊 STRUTTURA COMPLETA META FIELDS

### **1. DATI IDENTIFICATIVI**
```php
// Base
$preventivo_id = get_the_ID();
$cliente_nome = get_post_meta($preventivo_id, '_btr_cliente_nome', true);
$cliente_email = get_post_meta($preventivo_id, '_btr_cliente_email', true);
$cliente_telefono = get_post_meta($preventivo_id, '_btr_cliente_telefono', true);

// Pacchetto
$pacchetto_id = get_post_meta($preventivo_id, '_btr_pacchetto_id', true);
$pacchetto_nome = get_post_meta($preventivo_id, '_btr_pacchetto_nome', true);
$durata = get_post_meta($preventivo_id, '_btr_durata', true);
$durata_giorni = get_post_meta($preventivo_id, '_btr_durata_giorni', true);

// Date
$check_in_date = get_post_meta($preventivo_id, '_btr_data_check_in', true);
$check_out_date = get_post_meta($preventivo_id, '_btr_data_check_out', true);
$selected_date = get_post_meta($preventivo_id, '_btr_selected_date', true);
```

### **2. PARTECIPANTI E QUANTITÀ**
```php
// Totali
$num_adulti = intval(get_post_meta($preventivo_id, '_btr_num_adulti', true));
$num_bambini = intval(get_post_meta($preventivo_id, '_btr_num_bambini', true));
$num_neonati = intval(get_post_meta($preventivo_id, '_btr_num_neonati', true));

// Dettaglio fasce bambini
$bambini_f1 = intval(get_post_meta($preventivo_id, '_btr_bambini_f1', true));
$bambini_f2 = intval(get_post_meta($preventivo_id, '_btr_bambini_f2', true));
$bambini_f3 = intval(get_post_meta($preventivo_id, '_btr_bambini_f3', true));
$bambini_f4 = intval(get_post_meta($preventivo_id, '_btr_bambini_f4', true));

// Etichette categorie bambini
$child_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
// Esempio: ['f1' => '3-6 anni', 'f2' => '6-12', 'f3' => '12-14', 'f4' => '14-15']
```

### **3. CAMERE E ASSEGNAZIONI** ⭐ **SEZIONE CRITICA**
```php
// ✅ CAMPO CORRETTO DA USARE
$booking_data = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
$camere_con_assegnazioni = $booking_data['rooms'] ?? [];

// Struttura di ogni camera:
foreach ($camere_con_assegnazioni as $index => $camera) {
    $tipo = $camera['tipo'];                    // "Doppia/Matrimoniale", "Tripla"
    $quantita = $camera['quantita'];            // 1, 2, 3...
    $adulti = $camera['assigned_adults'] ?? 0;
    $bambini_f1 = $camera['assigned_child_f1'] ?? 0;
    $bambini_f2 = $camera['assigned_child_f2'] ?? 0;
    $bambini_f3 = $camera['assigned_child_f3'] ?? 0;
    $bambini_f4 = $camera['assigned_child_f4'] ?? 0;
    $neonati = $camera['assigned_infants'] ?? 0;
    $totale_camera = $camera['totale_camera'] ?? 0;
    $prezzo_base = $camera['prezzo_per_persona'] ?? 0;
    $supplemento = $camera['supplemento'] ?? 0;
}

// ❌ CAMPO OBSOLETO (NON USARE)
$camere_obsolete = get_post_meta($preventivo_id, '_btr_camere_selezionate', true);
// Questo ha tutti i totali a 0!
```

### **4. ANAGRAFICI DETTAGLIATI**
```php
// Array completo partecipanti
$anagrafici = get_post_meta($preventivo_id, '_btr_anagrafici', true);

// Oppure campi individuali (per X da 0 a 5):
$nome = get_post_meta($preventivo_id, "_anagrafico_{$X}_nome", true);
$cognome = get_post_meta($preventivo_id, "_anagrafico_{$X}_cognome", true);
$email = get_post_meta($preventivo_id, "_anagrafico_{$X}_email", true);
$telefono = get_post_meta($preventivo_id, "_anagrafico_{$X}_telefono", true);
$data_nascita = get_post_meta($preventivo_id, "_anagrafico_{$X}_data_nascita", true);
$citta_nascita = get_post_meta($preventivo_id, "_anagrafico_{$X}_citta_nascita", true);
$codice_fiscale = get_post_meta($preventivo_id, "_anagrafico_{$X}_codice_fiscale", true);
$camera = get_post_meta($preventivo_id, "_anagrafico_{$X}_camera", true);
$camera_tipo = get_post_meta($preventivo_id, "_anagrafico_{$X}_camera_tipo", true);
```

### **5. COSTI EXTRA PER PERSONA**
```php
// Per ogni partecipante X e ogni tipo di costo:
$extra_selected = get_post_meta($preventivo_id, "_anagrafico_{$X}_extra_{$tipo}_selected", true);
$extra_price = get_post_meta($preventivo_id, "_anagrafico_{$X}_extra_{$tipo}_price", true);

// Esempi di $tipo:
// - riduzione_no_skipass
// - supplemento_animale_domestico  
// - culla_per_neonati
// - etc.

// Totale costi extra calcolato
$totale_costi_extra = get_post_meta($preventivo_id, '_pricing_totale_costi_extra', true);
```

### **6. PRICING E TOTALI** ⭐ **SEMPRE AGGIORNATI**
```php
// Prezzi base
$prezzo_base_pp = get_post_meta($preventivo_id, '_pricing_prezzo_base_per_persona', true);
$supplemento_pp = get_post_meta($preventivo_id, '_pricing_supplemento_per_persona', true);

// Totali per categoria
$totale_adulti = get_post_meta($preventivo_id, '_pricing_totale_adulti', true);
$totale_bambini_f1 = get_post_meta($preventivo_id, '_pricing_totale_bambini_f1', true);
$totale_bambini_f2 = get_post_meta($preventivo_id, '_pricing_totale_bambini_f2', true);
$totale_bambini_f3 = get_post_meta($preventivo_id, '_pricing_totale_bambini_f3', true);
$totale_bambini_f4 = get_post_meta($preventivo_id, '_pricing_totale_bambini_f4', true);

// Totali camere
$totale_camere = get_post_meta($preventivo_id, '_pricing_totale_camere', true);
$totale_supplementi = get_post_meta($preventivo_id, '_pricing_totale_supplementi', true);

// Notti extra
$notti_extra_flag = get_post_meta($preventivo_id, '_pricing_notti_extra_flag', true);
$notti_extra_numero = get_post_meta($preventivo_id, '_pricing_notti_extra_numero', true);
$notti_extra_data = get_post_meta($preventivo_id, '_pricing_notti_extra_data', true);
$totale_notti_extra = get_post_meta($preventivo_id, '_pricing_totale_notti_extra', true);

// Totale finale
$totale_generale = get_post_meta($preventivo_id, '_pricing_total_price', true);
```

### **7. BREAKDOWN DETTAGLIATO**
```php
$breakdown = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);

// Struttura:
$partecipanti = $breakdown['partecipanti'] ?? [];
// ['adulti' => [...], 'bambini_f1' => [...], etc.]

$notti_extra = $breakdown['notti_extra'] ?? [];
// ['attive' => true/false, 'numero' => X, 'data' => '...']

$rooms_data = $breakdown['rooms'] ?? [];
// Dettagli per ogni camera
```

### **8. NOTTI EXTRA**
```php
$extra_night_flag = get_post_meta($preventivo_id, '_btr_notti_extra_flag', true);
$extra_night_number = get_post_meta($preventivo_id, '_btr_notti_extra_numero', true);
$extra_night_date = get_post_meta($preventivo_id, '_btr_extra_night_date', true);
$extra_night_pp = get_post_meta($preventivo_id, '_pricing_extra_night_pp', true);
```

### **9. STATO E VALIDITÀ**
```php
$stato = get_post_meta($preventivo_id, '_stato_preventivo', true);
$validity_days = get_post_meta($preventivo_id, 'btr_quote_validity_days', true);
$creation_date = get_the_date('Y-m-d', $preventivo_id);
$is_expired = /* calcolo basato su creation_date + validity_days */;
```

---

## 🚨 PROBLEMI IDENTIFICATI E SOLUZIONI

### **Problema 1: Tabella Camere Usa Dati Obsoleti**
```php
// ❌ PROBLEMA (dati a 0)
$camere = get_post_meta($preventivo_id, '_btr_camere_selezionate', true);

// ✅ SOLUZIONE
$booking_data = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
$camere = $booking_data['rooms'] ?? [];
```

### **Problema 2: Costi Extra Inconsistenti**
```php
// Verifica differenze
$saved_total = get_post_meta($preventivo_id, '_pricing_totale_costi_extra', true);
$calculated_total = get_post_meta($preventivo_id, '_btr_totale_costi_extra_calcolato', true);

if ($saved_total !== $calculated_total) {
    error_log("BTR Warning: Differenza costi extra: salvato {$saved_total}, calcolato {$calculated_total}");
}
```

---

## 🔧 FUNZIONI HELPER RACCOMMANDATE

```php
/**
 * Recupera dati camere corretti (sempre aggiornati)
 */
function btr_get_camere_corrette($preventivo_id) {
    $booking_data = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
    return $booking_data['rooms'] ?? [];
}

/**
 * Recupera totale finale sempre aggiornato
 */
function btr_get_totale_finale($preventivo_id) {
    return floatval(get_post_meta($preventivo_id, '_pricing_total_price', true));
}

/**
 * Recupera costi extra per partecipante
 */
function btr_get_costi_extra_partecipante($preventivo_id, $partecipante_index) {
    $all_meta = get_post_meta($preventivo_id);
    $costi = [];
    
    foreach ($all_meta as $key => $value) {
        $pattern = "/^_anagrafico_{$partecipante_index}_extra_(.+)_(.+)$/";
        if (preg_match($pattern, $key, $matches)) {
            $tipo = $matches[1];
            $field = $matches[2]; // 'selected' o 'price'
            $costi[$tipo][$field] = $value[0];
        }
    }
    
    return $costi;
}
```

---

## 📝 NOTE FINALI

- **Preferire sempre i campi con prefisso `_pricing_`** per totali
- **Usare `_btr_booking_data_json['rooms']`** per dati camere
- **Verificare sempre che i dati esistano** prima di usarli
- **I campi senza prefisso BTR possono essere legacy**
- **Testare sempre con preventivi recenti** (v1.0.157+)

---

**Ultimo aggiornamento**: 17 Gennaio 2025  
**Testato su**: Preventivo #36721 (Born to Ride Weekend)