# Born to Ride - WooCommerce Integration Overview

## Panoramica Integrazione

Il sistema Born to Ride estende WooCommerce trasformandolo da e-commerce prodotti fisici a piattaforma di booking viaggi complessa, mantenendo compatibilità con l'ecosistema WC.

## Punti di Estensione Principali

### 1. **Product System**
**Estensione**: Da prodotti semplici/variabili a pacchetti viaggio dinamici  
**Come funziona**: Ogni pacchetto viaggio (`btr_pacchetti`) genera automaticamente un WooCommerce Product con variazioni per date e configurazioni camere  
**Customizzazioni**: Meta fields aggiuntivi per durata, destinazione, allotment, prezzi stagionali

### 2. **Cart Management**
**Estensione**: Cart items arricchiti con dati partecipanti e configurazioni  
**Come funziona**: Session storage esteso per mantenere anagrafiche, assegnazioni camere, costi extra selezionati  
**Customizzazioni**: Cart validation custom per verificare disponibilità real-time, calcoli prezzi dinamici basati su età partecipanti

### 3. **Checkout Process**
**Estensione**: Multi-step flow con pagina intermedia selezione pagamento  
**Come funziona**: Intercetta checkout standard per aggiungere step selezione modalità pagamento prima del completamento  
**Customizzazioni**: Form fields popolati da dati preventivo, validazioni extra per codici fiscali e anagrafiche complete

### 4. **Order Management**
**Estensione**: Stati ordine custom per gestire caparra/saldo  
**Come funziona**: Nuovi stati (`deposit-paid`, `awaiting-balance`, `fully-paid`) per tracciare pagamenti parziali  
**Customizzazioni**: Meta data estesi per collegare ordini a preventivi, partecipanti, piani pagamento

## Sistema Caparra e Saldo

### Architettura Deposit/Balance

**Flusso Operativo**:
1. **Selezione**: Utente sceglie percentuale acconto (10-90%) tramite slider
2. **Calcolo**: Sistema applica fee negativa per ridurre totale checkout all'acconto
3. **Tracciamento**: Ordine mantiene riferimenti a importo totale, acconto pagato, saldo dovuto
4. **Follow-up**: Generazione automatica link pagamento saldo con scadenze configurabili

**Gestione Stati**:
- **Caparra Pagata**: Ordine confermato ma parziale
- **In Attesa Saldo**: Reminder automatici attivi
- **Pagamento Completo**: Viaggio confermato definitivamente

### Integrazione Gateway

**Compatibilità Testata**:
- PayPal Standard (redirect mode)
- Stripe (embedded checkout)
- Bonifico Bancario (offline processing)

**Personalizzazioni Gateway**:
- Intercettazione amount per pagamenti parziali
- Metadata aggiuntivi per reconciliation
- Webhook handling per aggiornamenti stato asincroni

## Pagamenti di Gruppo

### Meccanismo Split Payment

**Architettura**:
- **Payment Links**: Generazione URL univoci con token sicuri (7gg validità)
- **Quote Tracking**: Tabella `btr_order_shares` per tracciare chi deve pagare cosa
- **Status Aggregation**: Dashboard unificata per monitorare pagamenti multipli

**Flusso Email**:
1. Generazione link individuali post-checkout
2. Email automatiche con link personalizzato e scadenza
3. Reminder a 3gg dalla scadenza
4. Notifica organizzatore quando tutti hanno pagato

## Rischi e Compatibilità

### Potenziali Conflitti

**WooCommerce Updates**:
- **Rischio**: Breaking changes in cart/checkout API  
- **Mitigazione**: Version locking, regression testing su major updates

**Plugin Terze Parti**:
- **Checkout Managers**: Possibili conflitti con field customization
- **Payment Gateways**: Non tutti supportano amount manipulation
- **Email Customizers**: Override template potrebbero perdere dati custom

### Aree Sensibili

**Performance**:
- Calcoli prezzi complessi in cart può rallentare checkout
- Multiple query per verificare disponibilità real-time
- Session storage pesante con molti partecipanti

**Data Integrity**:
- Sincronizzazione bidirezionale pacchetti-prodotti
- Consistency tra preventivi e ordini
- Gestione cancellazioni e refund parziali

## Hook e Filter Critici

### Actions Utilizzati
- `woocommerce_checkout_init` - Inizializzazione modalità caparra
- `woocommerce_checkout_create_order` - Salvataggio meta custom
- `woocommerce_order_status_changed` - Trigger per azioni post-pagamento
- `woocommerce_cart_calculate_fees` - Applicazione sconti caparra

### Filters Modificati
- `woocommerce_checkout_fields` - Aggiunta campi preventivo
- `wc_order_statuses` - Registrazione stati custom
- `woocommerce_cart_item_data` - Arricchimento dati cart item
- `woocommerce_email_classes` - Email custom per caparra/saldo

## Compatibilità Store API

### REST Endpoints
**Supporto Parziale**: Il sistema usa principalmente legacy checkout, non fully compatible con Store API blocks

**Limitazioni**:
- Checkout block non supporta field customization completa
- Cart block manca hooks per fee manipulation
- Payment request buttons bypassano selezione modalità

### Migrazione Futura
**Piano**: Valutazione riscrittura per Blocks/Store API quando feature parity raggiunta

**Prerequisiti**:
- Extensibility API per checkout steps custom
- Support per complex cart calculations
- Hook parity con legacy system

## Best Practices Mantenimento

### Testing
- Test suite per ogni WooCommerce major update
- Smoke test checkout flow dopo plugin updates
- Monitoring performance checkout in produzione

### Documentazione
- Changelog dettagliato per ogni modifica WC-related
- Compatibility matrix con versioni WC/WP
- Troubleshooting guide per conflitti comuni

### Backup Strategy
- Snapshot pre-aggiornamenti WooCommerce
- Rollback plan per incompatibilità critiche
- Staging environment per test updates

## Moduli Non Integrati (TBD)

### Subscriptions
Piano per viaggi ricorrenti con WooCommerce Subscriptions - **analisi in corso**

### Bookings
Valutazione WooCommerce Bookings per gestione disponibilità avanzata - **non prioritario**

### Multi-Vendor
Possibile estensione per agenzie partner tramite marketplace - **roadmap futura**