# Integrazione Sistema Pagamenti di Gruppo

## Overview
Il sistema di pagamenti di gruppo è ora completamente integrato nel flusso principale del plugin Born to Ride Booking.

## Flusso Operativo

### 1. Selezione Piano di Pagamento
Quando un utente seleziona "Pagamento di Gruppo" dalla pagina di selezione del piano di pagamento:

1. I dati dei partecipanti vengono salvati in sessione
2. La classe `BTR_Group_Payments` genera automaticamente i link di pagamento individuali
3. I link vengono salvati nel database (tabelle `btr_group_payments` e `btr_payment_links`)
4. L'utente viene reindirizzato alla pagina di riepilogo link

### 2. Pagina Riepilogo Link
URL: `/payment-links-summary/`

La pagina mostra:
- Tabella con tutti i partecipanti e i loro link di pagamento
- Stato di ogni pagamento (in attesa/pagato)
- Azioni disponibili:
  - Copia link
  - Invia email singola
  - Mostra QR code
  - Invia email a tutti i partecipanti
  - Stampa riepilogo

### 3. Email Automatiche
Se l'opzione `btr_auto_send_payment_links` è attiva (default: yes), le email vengono inviate automaticamente a tutti i partecipanti dopo la generazione dei link.

### 4. Gestione Admin
Gli amministratori possono:
- Visualizzare lo stato di tutti i pagamenti dal menu admin
- Rigenerare link scaduti
- Inviare promemoria
- Monitorare le statistiche dei pagamenti

## Componenti Principali

### Class: BTR_Payment_Selection_Shortcode
**File**: `includes/class-btr-payment-selection-shortcode.php`

Modifiche:
- Integrazione con `BTR_Group_Payments` nel metodo `handle_create_payment_plan()`
- Nuovo shortcode `[btr_payment_links_summary]` per visualizzare il riepilogo
- Metodo `send_all_payment_emails()` per invio email automatico

### Class: BTR_Group_Payments
**File**: `includes/class-btr-group-payments.php`

Funzionalità esistenti utilizzate:
- `generate_group_payment_links()` - Genera link per tutti i partecipanti
- `send_payment_link_email()` - Invia email con link di pagamento
- `get_payment_stats()` - Ottiene statistiche dei pagamenti

Nuove aggiunte:
- Rewrite rule per `/payment-links-summary/`
- Gestione della pagina di riepilogo tramite `handle_payment_page()`

### Template: payment-links-summary.php
**File**: `templates/frontend/payment-links-summary.php`

Nuovo template che mostra:
- Informazioni del preventivo
- Tabella interattiva con tutti i link
- Azioni per copiare link, inviare email, generare QR
- Integrazione JavaScript per funzionalità dinamiche

## Configurazione

### Opzioni Database
- `btr_payment_links_summary_page` - ID della pagina per il riepilogo (opzionale)
- `btr_auto_send_payment_links` - Invia email automaticamente (default: yes)

### Meta Preventivo
- `_btr_payment_links_generated` - Flag che indica se i link sono stati generati
- `_btr_payment_links_generated_at` - Timestamp generazione link

## Utilizzo

### Per gli Utenti
1. Dalla pagina di selezione piano, scegliere "Pagamento di Gruppo"
2. Selezionare i partecipanti e le quote
3. Cliccare "Crea Piano di Pagamento"
4. Visualizzare il riepilogo con tutti i link generati
5. I partecipanti ricevono automaticamente le email con i loro link personali

### Per gli Admin
1. Accedere a "Preventivi > Pagamenti di Gruppo"
2. Selezionare un preventivo per vedere lo stato dei pagamenti
3. Utilizzare le azioni disponibili per gestire i pagamenti

## API JavaScript

Il template include funzioni JavaScript per:
- Copiare link negli appunti
- Inviare email via AJAX
- Mostrare QR code (placeholder per futura implementazione)
- Inviare email a tutti i partecipanti con delay progressivo

## Sicurezza
- Nonce verification per tutte le chiamate AJAX
- Capability checks per operazioni admin
- Link di pagamento con hash SHA256 sicuri
- Scadenza automatica dei link dopo 72 ore

## Future Implementazioni
- [ ] Generazione reale QR code con libreria dedicata
- [ ] Dashboard con grafici statistiche pagamenti
- [ ] Sistema di promemoria automatici
- [ ] Export dati pagamenti in CSV/Excel
- [ ] Integrazione con sistemi di notifica SMS