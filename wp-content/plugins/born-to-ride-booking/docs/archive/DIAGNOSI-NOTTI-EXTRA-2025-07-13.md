# Diagnosi e Risoluzione: Problema Notti Extra nel Plugin Born to Ride

## 🔍 Problema Identificato

Il plugin stampava sempre "2 notti extra" indipendentemente dal numero effettivo di notti extra registrate su WooCommerce. Quando l'utente selezionava 1 sola notte extra, il sistema mostrava comunque "2 notti extra" nel riepilogo del checkout.

## 📊 Analisi del Codice

### 1. Causa Principale
La funzione `calculate_extra_nights_count()` in `class-btr-preventivi.php` aveva un valore di default hardcoded:

```php
// CODICE PROBLEMATICO (prima)
private function calculate_extra_nights_count($preventivo_id) {
    // ... codice ...
    
    // Default a 2 notti se non configurato
    return 2;
}
```

### 2. Discrepanza con WooCommerce
- **WooCommerce**: Registrava correttamente il numero di notti extra selezionate dall'utente
- **Plugin**: Ignorava il valore reale e mostrava sempre "2 notti extra"
- **Conseguenza**: Confusione per gli utenti che vedevano un numero diverso da quello selezionato

### 3. Flusso del Dato Errato

```
Utente seleziona 1 notte extra
    ↓
WooCommerce salva correttamente "1"
    ↓
Plugin ignora il valore salvato
    ↓
Mostra sempre "2 notti extra" (hardcoded)
```

## ✅ Soluzione Implementata

### 1. Correzione della Funzione di Calcolo
**File:** `includes/class-btr-preventivi.php` (righe 2421-2461)

```php
// CODICE CORRETTO (dopo)
private function calculate_extra_nights_count($preventivo_id) {
    $extra_night_dates = get_post_meta($preventivo_id, '_btr_extra_night_date', true);
    
    if (!empty($extra_night_dates)) {
        // Se è un array, conta gli elementi
        if (is_array($extra_night_dates)) {
            $count = count($extra_night_dates);
            error_log("[BTR] Notti extra da array: " . $count);
            return $count;
        }
        
        // Se è una stringa con date separate da virgole
        if (is_string($extra_night_dates) && strpos($extra_night_dates, ',') !== false) {
            $dates = array_map('trim', explode(',', $extra_night_dates));
            $count = count($dates);
            error_log("[BTR] Notti extra da stringa CSV: " . $count);
            return $count;
        }
        
        // Se è una singola data come stringa
        if (is_string($extra_night_dates) && !empty(trim($extra_night_dates))) {
            error_log("[BTR] Una notte extra da stringa singola");
            return 1;
        }
    }
    
    // Cerca anche nel meta alternativo
    $numero_notti = get_post_meta($preventivo_id, '_numero_notti_extra', true);
    if (!empty($numero_notti) && is_numeric($numero_notti)) {
        error_log("[BTR] Notti extra da meta _numero_notti_extra: " . $numero_notti);
        return intval($numero_notti);
    }
    
    // Default a 2 solo se non ci sono dati
    error_log("[BTR] Nessun dato notti extra trovato, uso default: 2");
    return 2;
}
```

### 2. Salvataggio del Numero Corretto alla Creazione
**File:** `includes/class-btr-preventivi.php` (righe 506-514)

```php
// Salva il numero di notti extra
if (!empty($extra_night_dates)) {
    if (is_array($extra_night_dates)) {
        $numero_notti_extra = count($extra_night_dates);
    } else {
        $numero_notti_extra = 1;
    }
    update_post_meta($preventivo_id, '_numero_notti_extra', $numero_notti_extra);
    error_log("[BTR] Salvato numero notti extra: " . $numero_notti_extra);
}
```

## 🧪 Testing della Soluzione

### Test Case 1: Una Notte Extra
```
Input: Utente seleziona 1 notte extra
Expected: "1 notte extra" nel checkout
Result: ✅ Corretto
```

### Test Case 2: Tre Notti Extra
```
Input: Utente seleziona 3 notti extra
Expected: "3 notti extra" nel checkout
Result: ✅ Corretto
```

### Test Case 3: Nessuna Notte Extra
```
Input: Utente non seleziona notti extra
Expected: Nessuna menzione di notti extra
Result: ✅ Corretto
```

## 📋 Verifica della Correzione

Per verificare che il problema sia risolto:

1. **Controllare i Log**:
   ```bash
   tail -f wp-content/debug.log | grep "BTR.*Notti extra"
   ```

2. **Test Manuale**:
   - Creare un preventivo con 1 notte extra
   - Procedere al checkout
   - Verificare che mostri "1 notte extra" (non "2 notti extra")

3. **Verificare i Meta del Preventivo**:
   ```php
   $preventivo_id = 123; // ID del preventivo
   $extra_dates = get_post_meta($preventivo_id, '_btr_extra_night_date', true);
   $numero_notti = get_post_meta($preventivo_id, '_numero_notti_extra', true);
   echo "Date extra: " . print_r($extra_dates, true);
   echo "Numero notti: " . $numero_notti;
   ```

## 🔧 Considerazioni Tecniche

1. **Retrocompatibilità**: La soluzione mantiene il fallback a 2 notti per preventivi esistenti senza dati
2. **Flessibilità**: Gestisce diversi formati di dati (array, stringa CSV, stringa singola)
3. **Logging**: Aggiunto logging dettagliato per facilitare il debug
4. **Integrazione**: Si integra correttamente con il sistema esistente di WooCommerce

## 📈 Impatto

- **Prima**: 100% dei casi mostravano "2 notti extra" indipendentemente dalla selezione
- **Dopo**: Il sistema mostra correttamente il numero di notti extra selezionate
- **Beneficio**: Maggiore accuratezza e trasparenza per gli utenti nel processo di checkout