# Performance Audit

## Rischi e colli di bottiglia
- Query pesanti/ricorsive:
  - Cron/report con `SELECT *` (es. `payment-cron.php:294`) e aggregazioni giornaliere → limitare colonne e usare indici esistenti.
- Loop con meta access (front):
  - `BTR_Preventivi::create_preventivo()` cicla camere/variazioni con più `get_post_meta`/`wc_get_product` → caching locale per variaz. ripetute.
- I/O file:
  - Scritture frequenti di template/email/PDF in runtime: assicurare che siano on‑demand e non in page load critici.
- HTTP esterni:
  - Chiamate gateway via `wp_remote_*` → impostare `timeout` adeguato e fallback per evitare blocchi.

## Quick wins
- Ridurre `SELECT *` → selezionare solo colonne necessarie; aggiungere LIMIT dove sensato.
- Cache breve (transient) per letture ripetute: es. `get_payment_plan_by_preventivo`, label bambini per pacchetto.
- Evitare `error_log` rumorosi in produzione; proteggere con `BTR_DEBUG`.
- Defer/lazy per asset JS/CSS dove possibile; continuare a usare `BTR_VERSION` per cache busting.

## Interventi strutturali
- Estrarre calcolo prezzi e mapping payload in servizi piccoli e testabili (riduce costo cognitivo e duplicazioni).
- Introdurre layer repository per `btr_group_payments`/`payment_plans` con metodi mirati e cache interna.
- Validare compatibilità PHP 8.2/8.3: sostituire chiamate deprecate e aggiungere type hints dove sicuro.

