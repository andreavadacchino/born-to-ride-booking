# 🎯 PUNTO DI RIPRISTINO v1.0.189 - FIX ASSEGNAZIONI CAMERE

**Data**: 17 Gennaio 2025
**Branch**: fix/calcoli-extra-notti-2025-01
**Stato**: ⚠️ IN PROGRESS - Fix parziale, problema con lettura assegnazioni

## 🔍 IL PROBLEMA

### Confusione Partecipanti/Camere
La funzione `render_riepilogo_preventivo_shortcode()` aveva:

1. **Logica inconsistente per F3/F4**:
   - F1/F2 usavano: `$bambini_in_questa_camera['f1']`
   - F3/F4 usavano: `$partecipanti['bambini_f3']['quantita']`

2. **Calcoli duplicati e contraddittori**:
   - Totali calcolati due volte con logiche diverse
   - Fallback che non considerava assegnazioni reali

3. **Problema indici camera**:
   - Confusione tra `$camera_index` e `$camera_array_index`

## ✅ FIX APPLICATI

### 1. Unificazione logica F3/F4
```php
// PRIMA (linea 2386)
if (!empty($partecipanti['bambini_f3']) && $partecipanti['bambini_f3']['quantita'] > 0)

// DOPO
if (!empty($bambini_in_questa_camera['f3']) && $bambini_in_questa_camera['f3'] > 0)
```

### 2. Aggiunta calcoli F3/F4 nel totale camera
```php
// Bambini F3
if (!empty($bambini_in_questa_camera['f3'])) {
    $f3_data = $partecipanti['bambini_f3'];
    $camera_subtotal += $bambini_in_questa_camera['f3'] * (($f3_data['prezzo_base_unitario'] ?? 0) + ($f3_data['supplemento_base_unitario'] ?? 0));
    if (!empty($notti_extra['attive']) && !empty($f3_data['notte_extra_unitario'])) {
        $camera_subtotal += $bambini_in_questa_camera['f3'] * (($f3_data['notte_extra_unitario'] ?? 0) + ($f3_data['supplemento_base_unitario'] ?? 0));
    }
}
```

## ⚠️ PROBLEMA RIMANENTE

Le assegnazioni non vengono lette correttamente da `booking_data['rooms']`:
- La Doppia mostra solo 1 adulto invece di 2
- Tutti i bambini finiscono nella Tripla #2
- La Tripla #3 rimane vuota

### Possibili cause:
1. I dati in `_btr_booking_data_json` non sono salvati correttamente
2. La struttura dell'array `rooms` è diversa da quella attesa
3. Gli indici delle camere non corrispondono

## 📝 FILE MODIFICATI

1. `includes/class-btr-preventivi.php`:
   - Linee 2386-2389: Fix condizione F3
   - Linee 2411-2414: Fix condizione F4
   - Linee 2473-2489: Aggiunta calcoli F3/F4 nel totale

2. `born-to-ride-booking.php`:
   - Versione aggiornata a 1.0.189

## 🧪 TEST

File di test: `tests/test-camera-assignments-v189.php`

Verifica:
1. Assegnazioni per camera
2. Breakdown calcoli
3. Consistenza dati
4. Preview rendering

## ⚠️ ATTENZIONE

**IL FIX NON È COMPLETO**: Le assegnazioni delle camere non vengono lette correttamente.
Bisogna verificare come vengono salvati i dati in `_btr_booking_data_json`.

---

**Prossimi step**: 
1. Debug struttura `booking_data['rooms']`
2. Verificare salvataggio assegnazioni
3. Correggere lettura indici camere