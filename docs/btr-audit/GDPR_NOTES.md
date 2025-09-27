# Note GDPR & Protezione Dati

## Dati Personali (PII) trattati
- Preventivi: nome, email, telefono, età/fasce bambini, composizione camere.
- Pagamenti: nome/cognome partecipante, email, importo quota, stato pagamento, eventuale CF (ordine WC).
- Log sicurezza: IP, user‑agent, azioni (email/CF parzialmente mascherati).

## Dove sono salvati
- Post meta `btr_preventivi` (`_cliente_*`, `_anagrafici_preventivo`, totali, etichette, ecc.).
- Tabelle custom: `wp_btr_group_payments`, `wp_btr_payment_plans`, `wp_btr_payment_reminders`.
- Ordini WooCommerce: meta `_btr_*`, dati fatturazione standard WC.
- File log: `wp-content/btr-security.log` (se abilitato).

## Minimizzazione e masking
- Mascherare sempre CF/email nei log (già parziale): estendere mascheramento su altri campi sensibili.
- Evitare di serializzare payload completi nei meta se non necessario (salvare solo subset minimo).

## Retention & diritto all’oblio
- Definire policy: es. preventivi non convertiti → purge dopo 180 giorni; `btr_group_payments` scaduti → purge dopo 90 giorni.
- Aggiungere comandi manutenzione (cron o admin tool) per purge selettivo.

## Trasferimenti e sicurezza
- Webhook/gateway: usare TLS, validare firma e limitare IP se documentato.
- Esportazione dati: fornire endpoint/admin export con filtro data range e masking.

