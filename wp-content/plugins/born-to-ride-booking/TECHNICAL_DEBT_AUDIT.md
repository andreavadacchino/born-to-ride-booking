# Analisi del Debito Tecnico: Plugin "Born to Ride Booking"

**Data Audit:** 2025-09-06
**Analista:** Gemini (Senior Developer Persona)

## 1. Verdetto Generale

Il plugin è in uno stato critico, schiacciato da un debito tecnico accumulato per anni. L'architettura è in decomposizione, le pratiche di sviluppo sono caotiche e mancano standard di ingegneria del software basilari. Il sistema è insicuro, instabile e la sua manutenzione è estremamente costosa e rischiosa.

*   **Qualità del Codice:** **Molto Bassa / Critica**. La presenza di errori fatali (`duplicate function definition`), vulnerabilità di sicurezza attive (`printr` backdoor) e la totale assenza di standard di codifica rendono la codebase inaffidabile.
*   **Stima Debito Tecnico Generale:** **85-95%**. La quasi totalità del codice richiede un intervento, che sia una riscrittura, un refactoring profondo o una messa in sicurezza.

---

## 2. File da Eliminare

### File Sicuri al 100% da Eliminare

Questi file sono spazzatura, note, script di debug o backup che non dovrebbero mai trovarsi in un repository di codice sorgente. La loro eliminazione è sicura e non avrà impatti funzionali.

**Debito Tecnico Risolto con questa azione: ~10%** (Pulizia e riduzione del rumore)

```
# Dalla root del plugin
/ANALISI-FLUSSO-COMPLETO.md
/ANALISI-PROBLEMA-NEONATI-2025-01-20.md
/ANALISI-REDIRECT-CHECKOUT-2025-01.md
/BUILD-SYSTEM.md
/CAMPI-INDIRIZZO-CONDIZIONALI.md
/CART-REDIRECT-ANALYSIS.md
/CHANGELOG-PAYMENT-SYSTEM.md
/CHANGELOG-UI-IMPROVEMENTS-2025-01-24.md
/CHANGELOG-v1.0.141-PAYLOAD-MAPPING-FIX.md
/CHANGELOG-v1.0.142-FRONTEND-AUTHORITY.md
/CHANGELOG-v1.0.98.md
/CLEANUP_SUMMARY.md
/CLEAR-CACHE-INSTRUCTIONS.md
/COSTI_EXTRA_IMPLEMENTATION.md
/DATABASE-AUTO-INSTALLER.md
/DEPLOYMENT-INSTRUCTIONS-v1.0.61.md
/DYNAMIC_EXTRA_NIGHTS.md
/DYNAMIC_LABELS_IMPLEMENTATION.md
/DYNAMIC-EXTRA-NIGHTS-2025-01-12.md
/EXTRA_COSTS_OPTIMIZATION.md
/EXTRA-COSTS-PER-NIGHT-DOCUMENTATION.md
/FIX-IMMEDIATO-v1.0.222.md
/FIX-SPLIT-BRAIN-CALCULATOR-v1.0.201.md
/FRONTEND_MODIFICATIONS.md
/GATEWAY-OPTIMIZATION.md
/IMMEDIATE-ACTION-PLAN.md
/IMPLEMENTATION_REPORT.md
/IMPLEMENTATION-PROGRESS.md
/INFANT-LOGIC-REFACTORING.md
/MINI-PRD-METODI_PAGAMENTO.md
/PATCH-v1.0.143-CANONICAL-HANDLER.patch
/PAYMENT-GROUP-TECHNICAL-SPECS.md
/PAYMENT-SELECTION-IMPLEMENTATION.md
/PAYMENT-SYSTEM-TASK-HIERARCHY.md
/README-prezzi-bambini.md
/REFACTORING-v1.0.148.md
/ROUTING-IMPLEMENTATION.md
/SECURITY-AUDIT-SQL-INJECTION-FIX.md
/SECURITY-FIXES-REPORT-v1.0.216.md
/SISTEMA-INSTALLAZIONE-AUTOMATICA.md
/SPLIT-BRAIN-SOLUTION-v2.0.md
/STYLE-INTEGRATION-GUIDE.md
/TASK-COMMANDS-PAYMENT-SYSTEM.md
/TASK-CREATE-COMMANDS.md
/test-child-assignment-reset.md
/test-debug-frontend.html
/TESTING_GUIDE.md
/WORKFLOW-PAYMENT-IMPLEMENTATION.md

# Dalla directory /tests
/tests/analisi-assegnazione-36721.php
/tests/debug-booking-data.php
/tests/debug-totale-generale.php
/tests/FIXED-AJAX-ERROR-2025-01-23.md
/tests/PAYMENT-SYSTEM-TEST-DOCUMENTATION.md
/tests/RIEPILOGO-FIX-SCONTI.md
/tests/simulazione-dati-reali.php
/tests/SOLUZIONE-APPLICATA-V2.md
/tests/STRATEGIA-RIORGANIZZAZIONE-PRODOTTI.md
/tests/test-ajax-endpoints.php
/tests/test-algoritmo-assegnazione.php
/tests/test-anagrafici-display.php
/tests/test-camera-assignments-v189.php
/tests/test-child-labels-debug.php
/tests/test-child-labels-v186.php
/tests/test-child-labels-v187.php
/tests/test-child-labels-v188.php
/tests/test-datepicker-birthdate.html
/tests/test-datepicker-manual-input.html
/tests/test-fix-tabella-v190.php
/tests/test-frontend-v3-integration.php
/tests/test-modern-datepicker.html
/tests/test-modern-select.html
/tests/test-payment-group-edge-cases.php
/tests/test-phone-autocomplete.html
/tests/test-shortcode-v1.0.199.php
/tests/test-tabella-v1.0.191.php
/tests/test-unified-calculator-integration.php
/tests/test-unified-calculator.php
```

### File la cui Eliminazione NON è Sicura al 100% (ma probabile)

Questi file sono probabilmente codice morto, residui di tentativi di refactoring. **NON ELIMINARE DIRETTAMENTE.** L'azione corretta è consolidare la logica in una singola classe "canonica" e, solo dopo aver verificato che la nuova classe è usata ovunque, procedere all'eliminazione delle versioni vecchie.

*   `/includes/class-btr-preventivi-v2.php`
*   `/includes/class-btr-preventivi-refactored.php`
*   `/includes/class-btr-quotes.php`
*   `/includes/class-btr-preventivi-canonical.php`
*   `/includes/class-btr-preventivi-ordini.php` (esiste una `v2`)
*   `/includes/class-btr-payment-gateway-integration.php` (esiste una `v2`)
*   `/includes/class-btr-payment-cron.php` (esiste una versione `enhanced`)
*   `/includes/class-btr-checkout-backup.php`
*   `/includes/class-btr-checkout-totals-fix.php` (commentato come disabilitato nel file principale)

---

## 3. Piano d'Azione per la Riduzione del Debito Tecnico

Questo piano è diviso in due fasi: un triage di emergenza per fermare l'emorragia e un piano strategico a lungo termine. La riscrittura completa rimane l'opzione consigliata.

### Fase 1: Triage (Azioni Immediate)

**Obiettivo:** Mettere in sicurezza il plugin e preparare il terreno per un vero refactoring.
**Debito Tecnico Risolto Stimato: 15-20%**

1.  **Pulizia Drastica:**
    *   **Azione:** Eliminare tutti i file elencati nella sezione "File Sicuri al 100% da Eliminare".
    *   **Comando:** `git rm <lista dei file>`
    *   **Perché:** Rimuovono rumore, riducono la superficie di confusione e puliscono il repository.

2.  **Risoluzione Vulnerabilità Critiche:**
    *   **Azione:** Modificare il file `born-to-ride-booking.php`.
        1.  Rimuovere la seconda definizione della funzione `btr_format_price()` per risolvere l'errore fatale.
        2.  Nella funzione `printr()`, ripristinare il controllo di sicurezza `if (!current_user_can("manage_options")) { return; }` o, meglio ancora, eliminare l'intera funzione `printr`.
    *   **Perché:** Previene un crash del sito e chiude una falla di sicurezza che espone dati di debug.

3.  **Introduzione Strumenti Base:**
    *   **Azione:** Creare un file `composer.json` nella root del plugin.
    *   **Contenuto Iniziale:**
        ```json
        {
            "name": "borntoride/booking-plugin",
            "description": "Plugin per la gestione delle prenotazioni di pacchetti viaggio con WooCommerce.",
            "type": "wordpress-plugin",
            "require-dev": {
                "squizlabs/php_codesniffer": "*",
                "phpstan/phpstan": "^1.0"
            },
            "autoload": {
                "psr-4": {
                    "BTR\\Booking\\": "src/"
                }
            }
        }
        ```
    *   **Azione:** Eseguire `composer install`. Creare una directory `src`.
    *   **Perché:** Introduce la gestione delle dipendenze, un autoloader PSR-4 e strumenti di analisi statica. È il primo passo fondamentale per modernizzare la codebase.

### Fase 2: Refactoring Strategico (Lungo Termine)

**Obiettivo:** Ristrutturare l'architettura, migliorare la manutenibilità e introdurre test.
**Debito Tecnico Risolto Stimato: 60-70%**

1.  **Migrazione all'Autoloader (Il Grande Lavoro):**
    *   **Azione:** Spostare, una per una, le classi dalla directory `/includes` alla nuova directory `/src`.
    *   **Workflow per ogni classe:**
        1.  Sposta `class-btr-example.php` in `src/Example.php`.
        2.  Aggiungi il namespace `namespace BTR\Booking;` al file.
        3.  Rinomina la classe da `BTR_Example` a `Example`.
        4.  Rimuovi il `require_once` corrispondente da `born-to-ride-booking.php`.
        5.  Cerca in tutta la codebase le istanze di `new BTR_Example()` e sostituiscile con `new \BTR\Booking\Example()`.
    *   **Perché:** Sostituisce il caotico sistema di `require_once` con un autoloader standard, migliorando performance e manutenibilità.

2.  **Consolidamento della Logica Duplicata:**
    *   **Azione:** Partendo dal "mostro dei preventivi" (`class-btr-preventivi*.php`).
        1.  Identificare la versione più recente/corretta (probabilmente `class-btr-preventivi-v4.php`).
        2.  Spostarla in `src/Quote/QuoteManager.php` (o un nome simile) seguendo il processo di migrazione sopra.
        3.  Ripercorrere il codice per assicurarsi che solo questa nuova classe venga utilizzata.
        4.  **Scrivere test di integrazione** per questa classe per coprire i casi d'uso principali.
        5.  Una volta sicuri, eliminare le altre 5+ versioni del file.
    *   **Perché:** Elimina la fonte più grande di confusione e bug, creando un'unica "source of truth".

3.  **Sostituzione delle Dipendenze Manuali:**
    *   **Azione:**
        1.  Rimuovere la directory `/lib/tcpdf`.
        2.  Aggiungere una libreria PDF moderna via Composer: `composer require tecnickcom/tcpdf` (o un'alternativa come `spipu/html2pdf`).
        3.  Aggiornare la classe che genera PDF per usare la nuova libreria caricata da Composer.
    *   **Perché:** Mette in sicurezza il plugin e semplifica gli aggiornamenti futuri.

4.  **Introduzione del Testing Automatizzato:**
    *   **Azione:**
        1.  Configurare `phpunit.xml` per lavorare con la nuova struttura in `/src` e i test in `/tests`.
        2.  Iniziare a scrivere **veri test unitari** nella directory `/tests/Unit` per le classi più semplici e critiche (es. classi di validazione, calcolatori).
    *   **Perché:** Crea una rete di sicurezza che previene regressioni e permette di fare refactoring con fiducia.
