# 🧾 Mini PRD — Modalità di Pagamento Flessibile (Caparra, Intero, o per Gruppi)

## 1. Obiettivo
Integrare nel flusso di checkout WooCommerce tre modalità di pagamento alternative per la prenotazione di pacchetti Born to Ride:

- 💳 Pagamento Intero (100%)
- 💰 Pagamento con Caparra + Saldo finale
- 👥 Pagamento a Gruppi (quote suddivise tra i partecipanti)

La logica si integra in un sistema già esistente in cui:
- Le persone sono già selezionate.
- L’ordine e il preventivo sono già creati.
- Le camere, assicurazioni ed extra sono già assegnati.

---

## 2. Integrazione nel Flusso WooCommerce

| Fase        | Integrazione                                                                 |
|-------------|------------------------------------------------------------------------------|
| Checkout    | Step intermedio: "Modalità di pagamento" tra il riepilogo e la conferma     |
| Backend     | Salvataggio tipo pagamento su meta ordine + gestione quote se attiva         |
| Frontend    | UI per selezione e assegnazione quote nel caso di pagamento a gruppi         |
| Email       | Inviare link di pagamento individuale ai partecipanti con istruzioni chiare |

---

## 3. Modalità Supportate

### 🔹 Modalità 1: Pagamento Intero
- Checkout WooCommerce classico
- Fattura unica intestata al referente
- Stato ordine: “completato” o “in attesa” a seconda del gateway

### 🔹 Modalità 2: Caparra + Saldo
- Importo caparra definito nel backend (valore fisso o %)
- Saldo da versare entro una data X o in loco
- Due voci distinte nell’ordine: `caparra`, `saldo`
- Fattura:
  - caparra: immediata
  - saldo: post-pagamento

### 🔹 Modalità 3: Pagamento a Gruppi
- L’utente seleziona “Pagamento a Gruppi” nel checkout
- Viene mostrata UI per:
  - Assegnare quote ai partecipanti (anche multiple)
  - Definire chi paga assicurazioni, extra, camere
- Il sistema genera:
  - Ordine master sospeso
  - Link individuali di pagamento
- Ogni partecipante può pagare separatamente
- Reminder automatici se una quota non viene pagata entro X giorni
- Prenotazione confermata solo al saldo completo di tutte le quote

---

## 4. Pagina di Pagamento Partecipante

Ogni partecipante accede a una pagina con:
- Dati personali e riepilogo della propria quota
- Totale da pagare con dettaglio servizi
- Selezione metodo di pagamento: Stripe / Satispay / Bonifico / altri
- Tracciamento del pagamento in tempo reale
- Stato aggiornato nel backend

---

## 5. Backend & Stato Ordine

- Modalità di pagamento salvata come meta (`_btr_payment_mode`)
- Quote salvate in tabella `btr_order_shares`:
  - ID ordine
  - ID partecipante
  - Importo assegnato
  - Metodo di pagamento
  - Stato pagamento (non pagato, pagato, scaduto)
- Backend WooCommerce mostra stato quote per ogni ordine
- Admin può:
  - Forzare pagamento (es. bonifico manuale)
  - Rigenerare link
  - Inviare reminder
  - Cancellare l’ordine se quote incomplete

---

## 6. Integrazione Tecnica

- Compatibilità con **WooCommerce Blocks** (React)
- Salvataggio dati custom con **update_post_meta()** e tabelle custom
- Shortcode admin: `[btr_order_status]` per frontend
- Eventuale hook per `woocommerce_thankyou` per trigger pagamento quote
- Email e reminder gestiti via `wp_schedule_event()` o transients

---

## ✅ Best Practice per lo Sviluppo

- ✅ Seguire la documentazione WooCommerce Blocks: https://developer.woocommerce.com/document/woocommerce-blocks/
- ✅ Attivare modalità di pensiero avanzato: edge case, rollback, cambi modalità
- ✅ Minimizzare bug: testing approfondito gateway e flussi incompleti
- ✅ Validazione lato server: chi può pagare cosa, limiti temporali
- ✅ Refactoring costante e codice modulare
- ✅ Linting & test automatici prima di ogni merge
- ✅ Commenti docblock per ogni funzione chiave