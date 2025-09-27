# Born to Ride - Gap Analysis (Requisiti vs Stato Attuale)

| Funzione | Stato | Note |
|---|---|---|
| Prenotazione pacchetti standard (multi-step, preventivo, conferma) | Implementato | Descritta in FLOWS_OVERVIEW, funzionante con calcolo prezzi real-time e integrazione WooCommerce. |
| Gestione allotment camere (primario/secondario) | Parziale | Allotment base presente; gestione avanzata secondario e vincoli non completa. |
| Pagamenti caparra/saldo | Implementato | Integrato con PayPal e Stripe; logica caparra/saldo attiva. |
| Pagamenti di gruppo | Parziale | Link individuali implementati (v1.0.99); gestione scadenze e reminder presenti, ma flusso gruppi >10 persone da affinare. |
| Codici sconto | Non verificato | Non menzionato nei file .md, ma richiesto nei requisiti. |
| Integrazione Satispay | Mancante | Non rilevata nei file .md; prevista nei requisiti. |
| Esportazione dati (PDF/Excel) | Implementato | PDF generation presente (TCPDF); export Excel non citato, da verificare. |
| Noleggi attrezzatura | Mancante | Non implementato; specifiche complete nei requisiti. |
| Lezioni | Mancante | Non implementato; specifiche complete nei requisiti. |
| Fatturazione automatica | Parziale | Generazione PDF presente; integrazione con sistema fiscale da verificare. |
| Notifiche via WhatsApp | Mancante | Previsto nei requisiti; non menzionato nei file correnti. |
| Gestione assicurazioni e supplementi pro-quota | Parziale | Gestione supplementi base implementata; calcolo dinamico assicurazioni da verificare. |