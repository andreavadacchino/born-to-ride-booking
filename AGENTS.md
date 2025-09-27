# Repository Guidelines

## Struttura del Progetto
- Codice sorgente: `wp-content/plugins/born-to-ride-booking/` (plugin custom) e tema `wp-content/themes/salient/`.
- Test: `./tests/` con configurazione `phpunit.xml` in root.
- Risorse/asset: all’interno dei rispettivi plugin/tema; upload runtime in `wp-content/uploads/`.
- Documentazione tecnica: file `.md` in root; audit in `./docs/btr-audit/`.

## Build, Test e Sviluppo
- Test unit/integration: `vendor/bin/phpunit -c phpunit.xml` (oppure `phpunit -c phpunit.xml` se globale).
- Lint rapido: `php -l path/to/file.php`.
- PHPCS (se configurato): `phpcs --standard=WordPress wp-content/plugins/born-to-ride-booking`.
- Ambiente locale: WordPress standard; nessun build obbligatorio.

## Stile e Convenzioni
- PHP: 4 spazi, WPCS (WordPress Coding Standards), niente trailing spaces.
- Nomi: funzioni `btr_foo_bar()`, classi `StudlyCaps`, file plugin kebab-case `.php`.
- Sicurezza: usa API WP (`sanitize_text_field`, `esc_html`, `wp_verify_nonce`, `current_user_can`), mai interpolare SQL; preferire `$wpdb->prepare()`.
- Traduzioni: `__()`, `_e()`, domini coerenti col plugin.

## Linee Guida Test
- Posizionamento: `tests/Unit/` e `tests/Integration/`; nome file `*Test.php`.
- Copertura minima consigliata: funzioni critiche (preventivo/allotment, WooCommerce) >80%.
- Dati: usa fixture/mocks; non toccare `wp-content/uploads/` reali.

## Commit e Pull Request
- Messaggi: stile storico del repo, es. `v1.0.234: breve descrizione del cambiamento`.
- PR: descrizione chiara, passi di test, issue collegata, impatti su sicurezza/performance, screenshot ove utile.
- Check richiesti: test verdi, lint senza errori, nessun file sensibile aggiunto.

## Note di Sicurezza & Configurazione
- Dati sensibili: non versionare credenziali/chiavi; usare costanti in `wp-config.php` o variabili d’ambiente.
- GDPR: non loggare PII; minimizzare dati nei log; mascherare email/telefoni.
- WooCommerce: validare/sanificare metadati ordine; capacità utente prima di azioni amministrative.
