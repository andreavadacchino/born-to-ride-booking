# Test Plan (Priorità)

## Unit
- Price calc: casi adulti/bambini, notti extra, sconti/supplementi.
- Validator: `BTR_Payment_Security::validate_*` (hash, nonce, campi obbligatori, CF/CAP/email).
- Repository DB: CRUD `BTR_Database_Manager` (formati, indici minimi, edge cases).

## Integration
- AJAX preventivo: flusso completo con nonce valido/invalidi, payload JSON, meta salvati attesi.
- AJAX pagamenti: create plan → generate links → process group payment (ordine WC creato, meta `_btr_*`).
- REST payments: GET details → POST process (nonce) → webhook (firma) → stato aggiornato.

## E2E (felici/edge)
- Creazione preventivo → selezione piano → pagamento singolo (redirect gateway o WC fallback).
- Scadenze: link scaduto (410), rate‑limit superato (429).

## Strumenti qualità (proposta)
- PHPCS (WPCS): regole base sicurezza/escaping; eseguito in CI.
- PHPStan lvl 4‑6: type safety sulle classi manager/service.
- Coverage: usare `bin/run-tests.sh coverage` (report HTML in `tests/coverage-report/`).

