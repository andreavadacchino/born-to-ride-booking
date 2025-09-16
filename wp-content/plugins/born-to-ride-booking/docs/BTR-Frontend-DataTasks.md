# BTR Frontend Data Collection - Task List

## Obiettivo
Modificare `frontend-scripts.js` per inviare TUTTI i dati necessari al backend in forma strutturata e completa durante la prima fase di prenotazione.

## Task List

### Task 1: Analisi Stato Attuale ⏳
**Obiettivo**: Comprendere la struttura attuale del payload AJAX

#### Sub-task 1.1: Analizzare frontend-scripts.js
- [ ] Identificare tutte le chiamate AJAX esistenti
- [ ] Mappare i dati attualmente inviati
- [ ] Identificare i punti di raccolta dati nel form

#### Sub-task 1.2: Analizzare il backend handler
- [ ] Identificare l'endpoint AJAX (`wp_ajax_btr_create_preventivo`)
- [ ] Mappare i dati attesi dal backend
- [ ] Identificare gap tra frontend e backend

#### Sub-task 1.3: Mappare tutti i campi form
- [ ] Elencare tutti gli input del form multi-step
- [ ] Identificare dati calcolati runtime
- [ ] Mappare dati di sessione utilizzati

---

### Task 2: Progettazione Nuovo Payload
**Obiettivo**: Definire struttura JSON completa e organizzata

#### Sub-task 2.1: Definire schema dati
- [ ] Creare struttura gerarchica JSON
- [ ] Definire naming convention
- [ ] Documentare ogni campo

#### Sub-task 2.2: Validazione schema
- [ ] Verificare compatibilità WooCommerce
- [ ] Verificare compatibilità backend esistente
- [ ] Identificare campi obbligatori vs opzionali

---

### Task 3: Implementazione Raccolta Dati
**Obiettivo**: Modificare frontend-scripts.js per raccogliere tutti i dati

#### Sub-task 3.1: Creare funzione collectAllFormData()
- [ ] Implementare raccolta dati partecipanti
- [ ] Implementare raccolta configurazione viaggio
- [ ] Implementare raccolta costi e calcoli

#### Sub-task 3.2: Strutturare dati in payload
- [ ] Organizzare dati in oggetto strutturato
- [ ] Aggiungere metadata (timestamp, versione)
- [ ] Implementare sanitizzazione base

---

### Task 4: Integrazione AJAX
**Obiettivo**: Modificare chiamata AJAX per inviare nuovo payload

#### Sub-task 4.1: Aggiornare funzione AJAX
- [ ] Modificare serializzazione dati
- [ ] Aggiungere header appropriati
- [ ] Implementare retry logic

#### Sub-task 4.2: Error handling
- [ ] Gestire errori di rete
- [ ] Gestire errori di validazione
- [ ] Implementare feedback utente

---

### Task 5: Testing e Validazione
**Obiettivo**: Verificare correttezza e completezza

#### Sub-task 5.1: Test unitari
- [ ] Testare raccolta dati
- [ ] Testare serializzazione
- [ ] Testare edge cases

#### Sub-task 5.2: Test end-to-end
- [ ] Simulare prenotazione completa
- [ ] Verificare ricezione backend
- [ ] Validare calcoli e persistenza

---

### Task 6: Documentazione
**Obiettivo**: Documentare modifiche e nuovo formato

#### Sub-task 6.1: Documentare payload
- [ ] Creare esempio JSON completo
- [ ] Documentare ogni campo
- [ ] Aggiungere note migrazione

#### Sub-task 6.2: Aggiornare README
- [ ] Documentare breaking changes
- [ ] Aggiungere guida migrazione
- [ ] Includere esempi uso

---

## Stato Avanzamento

**Completati**: 0/6 task principali  
**In corso**: Task 1  
**Prossimo**: Sub-task 1.1

---

## Note di Sviluppo

*Timestamp inizio: 2025-01-25*

---