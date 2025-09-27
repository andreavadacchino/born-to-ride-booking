# Born to Ride - Panoramica Flussi Utente

## Flusso Prenotazione Standard

### 1. Selezione Pacchetto
**Cosa vede l'utente**: Lista pacchetti viaggio con date disponibili, prezzi base e stato disponibilità (Disponibile/Sold Out/Chiusa)  
**Cosa decide**: Quale pacchetto e data scegliere  
**Interazione**: Click su "Calcola Preventivo" per la data desiderata

### 2. Configurazione Viaggio
**Cosa vede l'utente**: Form multi-step con:
- Selezione numero partecipanti (adulti, bambini per fasce età, neonati)
- Scelta tipologia camere (singola, doppia, tripla, quadrupla)
- Costi extra opzionali (skipass, transfer, noleggi)
- Assicurazioni disponibili con coperture
- I costi estra sono dinamici impostati nel pacchetto dall'admin
- Le assicurazioni sono dinamiche e impostate nel pacchetto dall'admin

**Cosa decide**: 
- Composizione gruppo viaggio
- Distribuzione camere preferita
- Servizi aggiuntivi necessari
- Copertura assicurativa

**Calcolo totale** (principi generali):
- Prezzo base × numero partecipanti
- Sconti bambini applicati per fascia età (f1: 3-6 anni, f2: 6-8 anni, f3: 8-10 anni, f4: 11-12 anni)
- Supplementi camera (singola +40€, tripla/quadrupla possibili riduzioni)
- Costi extra selezionati (per persona o per notte)
- Assicurazioni (percentuale su totale o importo fisso)
- Totale mostrato in tempo reale con breakdown dettagliato
- gli sconti e le fascie di età sono dinamiche impostate dal pacchetto in admin

### 3. Dati Anagrafici
**Cosa vede l'utente**: Form per ogni partecipante con:
- Nome, cognome, data nascita
- Indirizzo completo, contatti
- Codice fiscale
- Assegnazione camera finale
- Progress bar con tempo rimanente (se attivo)

**Cosa decide**: 
- Chi dorme con chi (assegnazione camere definitive)
- Dati fiscali per fatturazione

### 4. Selezione Modalità Pagamento
**Cosa vede l'utente**: Tre opzioni principali:
- **Pagamento Completo**: Salda tutto subito online
- **Acconto + Saldo**: Slider per scegliere percentuale acconto (10-90%)
- **Pagamento di Gruppo**: Dividi tra partecipanti

**Cosa decide**: Come gestire il pagamento totale

### 5. Checkout e Conferma
**Cosa vede l'utente**: 
- Riepilogo completo prenotazione
- Form pagamento (carta credito, PayPal, bonifico)
- Termini e condizioni

**Cosa decide**: Conferma finale e metodo pagamento

## Modalità Pagamento Gruppo

### Differenze nel Flusso

#### Dopo Selezione "Pagamento di Gruppo"
**Cosa vede l'utente aggiuntivo**:
- Lista partecipanti con checkbox per selezione
- Campo importo per ogni partecipante selezionato
- Totale quote assegnate vs totale dovuto
- Opzione per equalizzare automaticamente

**Cosa decide**:
- Chi partecipa al pagamento
- Quanto paga ciascuno
- Se inviare subito i link o dopo

#### Post-Conferma
**Cosa succede**:
- Generazione link pagamento individuali (validi 7 giorni)
- Invio email automatico a ogni pagante con:
  - Link personalizzato
  - Importo dovuto
  - Scadenza pagamento
  - Dettagli viaggio

**Dashboard Organizzatore**:
- Stato pagamenti in tempo reale
- Chi ha pagato/chi manca
- Possibilità reinvio link
- Download riepilogo PDF

### Timeout e Sospensione
- **Link pagamento**: Scadenza 7 giorni (configurabile)
- **Sessione preventivo**: 30 minuti inattività
- **Reminder automatici**: 3 giorni prima scadenza
- **Gestione scaduti**: Possibilità rigenerazione link da admin

## Gestione Allotment Camere

### Principi Generali

**Capienza Base**:
- Ogni data ha un allotment totale definito (es. 50 posti)
- Suddiviso per tipologia camera disponibile
- Controllo real-time disponibilità durante selezione

**Regole Occupazione**:
- **Singola**: 1 adulto
- **Doppia**: 2 adulti O 1 adulto + 1 bambino
- **Tripla**: Fino a 3 persone (max 2 bambini)
- **Quadrupla**: Fino a 4 persone (max 3 bambini)
- **Quintupla**: Fino a 5 persone (max 4 bambini)
- **Neonati**: Occupano posto letto (culla su richiesta) sono non paganti

**Gestione Disponibilità**:
- Blocco preventivo per 30 minuti durante compilazione
- Conferma definitiva solo a pagamento completato
- Release automatico posti non confermati
- Overbooking gestito manualmente da admin

## Messaggi e Validazioni Principali

### Durante Configurazione
- ⚠️ **"Almeno un adulto richiesto"**: Ogni prenotazione necessita minimo 1 maggiorenne
- ❌ **"Capienza camera superata"**: Troppi ospiti per tipologia selezionata
- ℹ️ **"Culla disponibile su richiesta"**: Per neonati
- ✅ **"Configurazione valida"**: Tutto corretto, procedi

### Durante Anagrafici
- ⚠️ **"Campo obbligatorio"**: Dati mancanti essenziali
- ❌ **"Codice fiscale non valido"**: Formato errato CF
- ⚠️ **"Email già utilizzata"**: Per evitare duplicati stesso viaggio
- ℹ️ **"Tempo rimanente: XX minuti"**: Countdown sessione

### Durante Pagamento
- ✅ **"Quote correttamente assegnate"**: Totale diviso = totale dovuto
- ⚠️ **"Mancano €XX da assegnare"**: Divisione incompleta
- ❌ **"Importo superiore al dovuto"**: Errore calcolo quote
- ℹ️ **"Link pagamento inviati"**: Conferma invio email

### Post-Prenotazione
- ✅ **"Prenotazione confermata #XXXXX"**: Booking completato
- ℹ️ **"Email di conferma inviata"**: Con riepilogo e documenti
- ⚠️ **"Pagamento in attesa"**: Per acconti o gruppo
- ❌ **"Pagamento fallito"**: Con opzioni retry

## Moduli Non Implementati (TBD)

### Pacchetti Personalizzati
Creazione viaggi su misura con wizard dedicato - **in fase di progettazione**