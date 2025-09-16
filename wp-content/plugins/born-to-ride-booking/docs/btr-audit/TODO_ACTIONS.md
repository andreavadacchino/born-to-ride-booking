# Backlog Azioni (priorità, effort, rischio)

## Security
- [Alta|Bassa|Basso] Impostare `timeout` e `sslverify` espliciti in `BTR_Gateway_API_Manager` per tutte le `wp_remote_*`.
- [Media|Bassa|Basso] Ridurre superficie `nopriv` dove possibile o accorciare TTL dei nonce pubblici; documentare durata.
- [Media|Bassa|Basso] Riesame escaping nei template `templates/*.php` (audit mirato output HTML).

## Performance
- [Alta|Media|Basso] Sostituire `SELECT *` in cron/report con colonne mirate; aggiungere LIMIT.
- [Media|Bassa|Basso] Cache locale nel ciclo camere in `create_preventivo()` (variazioni e meta ripetuti).
- [Media|Bassa|Basso] Transient per `get_payment_plan_by_preventivo` e label bambini.

## Bugfix
- [Media|Bassa|Basso] Verificare compatibilità transazioni MySQL (host condivisi): fallback senza `START TRANSACTION` se non supportato.
- [Media|Bassa|Basso] Log file `btr-security.log`: controllare permessi/rotazione per non crescere indefinitamente.
- [Alta|Bassa|Basso] Conflitto handler `btr_save_anagrafici`: unificare sull’handler dello shortcode o allineare nonce come in PATCH_PROPOSAL_ANAGRAFICI.md.

## Refactor
- [Media|Media|Basso] Estrarre servizi: `RequestValidator`, `PriceCalculator`, `PaymentRepository`.
- [Media|Bassa|Basso] Introdurre interfacce e type hints non‑BC nelle classi nuove.

## DX
- [Media|Bassa|Basso] Abilitare PHPCS (WPCS) e PHPStan nel CI (solo proposta; nessuna modifica runtime).
- [Bassa|Bassa|Basso] Aggiornare guida sviluppatori: come generare build e come eseguire test con copertura.
