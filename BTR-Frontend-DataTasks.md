# BTR Frontend Data Tasks - Implementazione Split Dinamico

Data: 09/08/2025  
Obiettivo: Aggiungere payload "splittato" dinamico senza hardcode al POST AJAX esistente

## Task Overview

Implementare un sistema di flattening ricorsivo generico che traversa qualsiasi struttura JSON e genera campi in bracket notation `btr_flat[...]` senza mai hardcodare nomi di campi.

---

## Task 1: Analisi punto di invio AJAX ✅
**Obiettivo**: Identificare esattamente dove viene composto e inviato il POST

### Sub-task 1.1: Mappare costruzione oggetto ✅
- [x] Trovare dove viene creata `collectAllBookingData()` (riga 3280)
- [x] Identificare dove viene serializzato `booking_data_json` (riga 3805)
- [x] Verificare struttura FormData esistente (riga 3800)

### Sub-task 1.2: Identificare container dati ✅
- [x] Localizzare variabili globali (`window.btr_booking_form`) (class-btr-shortcodes.php:2074)
- [x] Mappare dati DOM utilizzati
- [x] Verificare presenza di state management

---

## Task 2: Introdurre feature flag `sendSplit` ✅
**Obiettivo**: Aggiungere flag controllabile da PHP senza side-effect

### Sub-task 2.1: Localizzare flag da PHP ✅
- [x] Trovare dove viene iniettato JS (class-btr-shortcodes.php:2059)
- [x] Aggiungere struttura `flags.sendSplit` con apply_filters
- [x] Verificare backward compatibility

### Sub-task 2.2: Test flag disabilitato ✅
- [x] Verificare che con `sendSplit=false` nulla cambi
- [x] Controllare che non ci siano log/errori

---

## Task 3: Implementare `addFlattened()` generico ✅
**Obiettivo**: Funzione di traversal ricorsivo senza hardcode

### Sub-task 3.1: Implementare traversal ricorsivo ✅
- [x] Gestione `object` con `Object.keys()`
- [x] Gestione `array` con indici numerici
- [x] Gestione primitive (foglie)
- [x] Costruzione path dinamica in bracket notation

### Sub-task 3.2: Normalizzazione valori ✅
- [x] number → String con punto decimale
- [x] boolean → "true"/"false"
- [x] null/undefined → stringa vuota
- [x] altri → String(value)

### Sub-task 3.3: Gestione dimensioni elevate ✅
- [x] Evitare duplicazioni di serializzazione
- [x] Considerare limiti FormData
- [x] Opzione per `btr_flat_raw_json` se necessario

---

## Task 4: Integrazione nel flusso di invio ✅
**Obiettivo**: Aggiungere split al POST esistente senza alterarlo

### Sub-task 4.1: Parse sicuro del JSON ✅
- [x] Try-catch su JSON.parse
- [x] Gestione errori graceful
- [x] Fallback se parse fallisce

### Sub-task 4.2: Applicare addFlattened condizionalmente ✅
- [x] Solo se `window.btrBooking?.flags?.sendSplit === true`
- [x] Chiamare `addFlattened(parsed, formData, 'btr_flat')`
- [x] Non modificare parametri esistenti

### Sub-task 4.3: Preservare compatibilità ✅
- [x] Verificare tutti i campi legacy presenti
- [x] Controllare che l'ordine non sia alterato
- [x] Test con flag on/off

---

## Task 5: Debug controllato e performance ⏳
**Obiettivo**: Log sicuri e ottimizzazione se necessario

### Sub-task 5.1: Debug condizionale ⏳
- [ ] Log solo se `window.btrBooking?.debug === true`
- [ ] Non loggare dati personali
- [ ] Console group per organizzazione

### Sub-task 5.2: Debounce se necessario ⏳
- [ ] Verificare se submit è frequente
- [ ] Implementare debounce conservativo se serve
- [ ] Test performance con oggetti grandi

---

## Task 6: Verifica completa output ⏳
**Obiettivo**: Confermare che tutte le foglie siano presenti

### Sub-task 6.1: Test manuale completo ⏳
- [ ] Verificare presenza di tutti i `btr_flat[...]`
- [ ] Controllare correttezza dei path
- [ ] Validare normalizzazione valori

### Sub-task 6.2: Test edge cases ⏳
- [ ] Array vuoti
- [ ] Oggetti nested profondi
- [ ] Valori speciali (null, undefined, NaN)

---

## Task 7: Documentazione e commenti ⏳
**Obiettivo**: Documentare per manutenibilità futura

### Sub-task 7.1: Commenti inline nel codice ⏳
- [ ] Spiegare logica addFlattened
- [ ] Documentare feature flag
- [ ] Note su estendibilità

### Sub-task 7.2: Header comment nel file ⏳
- [ ] Come attivare/disattivare sendSplit
- [ ] Formato chiavi btr_flat
- [ ] Esempio di output

---

## Stato Complessivo

**Completati**: 4/7 task principali ✅  
**In corso**: Task 5 (Debug e Performance)  
**Prossimo**: Task 6 (Verifica output)

---

## Note di Implementazione

- **NO HARDCODE**: ✅ Implementato con Object.keys() e Array.isArray()
- **Ricorsione generica**: ✅ Funziona con qualsiasi struttura JSON
- **Backward compatible**: ✅ Tutti i campi legacy preservati
- **Feature flag**: ✅ Controllabile via `apply_filters('btr_enable_split_payload', false)`
- **Funzione addFlattened**: Aggiunta a riga 3536 di frontend-scripts.js
- **Integrazione AJAX**: Aggiunta a riga 3808-3846 di frontend-scripts.js
- **PHP Support**: Aggiunto in class-btr-shortcodes.php righe 2061-2077
- **File di test**: Creato test-split-payload.php per verifiche

---

## Come Attivare il Feature

Per attivare il split payload dinamico, aggiungere nel tema o plugin:

```php
// Attiva split payload
add_filter('btr_enable_split_payload', '__return_true');

// Opzionale: includi anche JSON raw per debug
add_filter('btr_include_flat_raw_json', '__return_true');
```

---

## Log Modifiche

- 09/08/2025 - Documento creato
- 09/08/2025 - Implementati Task 1-4: addFlattened(), integrazione AJAX, feature flags
- 09/08/2025 - Creato file di test per verifica funzionalità