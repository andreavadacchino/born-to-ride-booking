# Manutenibilità & Qualità

## Codestyle e convenzioni
- PHP: aderire a WordPress Coding Standards (WPCS), 4 spazi, prefissi `BTR_`/`btr_`.
- File naming: classi `class-btr-*.php`, funzioni helper in `includes/helpers/`.

## Punti critici
- Metodi lunghi/monolitici: `BTR_Preventivi::create_preventivo()` contiene molte responsabilità (parsing, calcolo, I/O meta).
- Duplicazioni: mapping input bambini/fasce e calcoli prezzi ricorrono in più classi.
- Accoppiamento: accessi diretti a `$_POST`/`WC()->session` in vari punti → astrarre in servizi.

## Refactoring suggerito (incrementale, low‑risk)
1) Estrarre `RequestValidator` e `PriceCalculator` con interfacce semplici e test.
2) Introdurre `PaymentRepository` (btr_group_payments) con metodi get/update espliciti.
3) Ridurre surface dei metodi pubblici; favorire metodi privati piccoli e puri.
4) Applicare WPCS via PHPCS (solo CI o pre‑commit), regole base: escaping, i18n, naming.

## Documentazione
- Consolidare README tecnico per build/test (già presente in `BUILD-SYSTEM.md`, `TESTING-GUIDE.md`).

