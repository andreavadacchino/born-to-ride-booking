# Born to Ride - Architettura Sistema

## Panoramica

Sistema WordPress per gestione prenotazioni viaggi moto con integrazioni WooCommerce.

### Stack Tecnologico
- **WordPress** 5.0+ con localizzazione italiana
- **WooCommerce** 3.0+ per e-commerce
- **Tema Salient** v13.0.5 (premium con WPBakery Page Builder)  
- **Plugin Custom** Born to Ride Booking v1.0.109
- **PDF**: TCPDF per generazione documenti

## Struttura Cartelle

### Plugin Principale (`/wp-content/plugins/born-to-ride-booking/`)

**Core (`includes/`)**
- Classi sistema prenotazione e preventivi
- Integrazioni pagamento e gateway
- Sincronizzazione WooCommerce
- Database manager e migrations
- Sistema email e PDF generator

**Admin (`admin/`)**
- Dashboard e menu manager
- Ajax handlers per operazioni backend
- Vista diagnostica sistema
- Sistema build e release

**Frontend (`assets/`)**
- CSS unificato con design system
- JavaScript per form multi-step e calcoli
- Datepicker e select custom
- Script per checkout e pagamenti

**Templates (`templates/`)**
- Pagine selezione pagamento
- Form preventivi e review
- Template unified per coerenza UI

## Moduli Principali

### Post Types e Contenuti
- **Pacchetti** (`pacchetti`) - Pacchetti viaggio con prezzi e disponibilità
- **Preventivi** (`preventivi`) - Quote generate dal sistema

### Sistema Prenotazione
- **Form Multi-Step**: Selezione date → Partecipanti → Dati anagrafici → Pagamento
- **Calcolo Prezzi**: Adulti/bambini con fasce età dinamiche (f1-f4)
- **Costi Extra**: Per persona o per notte configurabili
- **Camere**: Gestione tipologie e assegnazioni

### Pagamenti (v1.0.99+)
- **Selezione Modalità**: Pagamento completo, acconto+saldo, gruppo
- **Group Payments**: Divisione quote tra partecipanti
- **Payment Plans**: Rateizzazione configurabile
- **Gateway Integration**: Supporto multipli gateway

### Integrazioni WooCommerce
- Sincronizzazione bidirezionale prodotti/ordini
- Gestione variazioni per date e camere
- Cart manager per extras e supplementi
- Store API extensions (TBD)

## Punti Estensione WordPress

### Hook Principali
Il sistema utilizza estensivamente WordPress hooks per integrazioni con WooCommerce checkout, cart calculations, order processing. Filtri per prezzi, disponibilità, validazioni sono distribuiti tra le classi core.

### Shortcode
- `[btr_booking_form]` - Form prenotazione principale
- `[btr_anagrafici_form]` - Raccolta dati partecipanti
- `[btr_payment_selection]` - Selezione modalità pagamento
- `[btr_riepilogo_preventivo]` - Review preventivo
- `[btr_seleziona_assicurazioni]` - Selezione assicurazioni

### Blocchi Gutenberg
- `btr-checkout-summary` - Riepilogo checkout (in sviluppo)

### WPBakery Integration
- Modulo pacchetto singolo con template multipli
- Configurazione date disponibili visuale

## Database Custom

### Tabelle Principali
- `btr_quotes` - Preventivi salvati
- `btr_quote_participants` - Partecipanti preventivo
- `btr_quote_rooms` - Assegnazione camere
- `btr_quote_extra_costs` - Costi aggiuntivi
- `btr_group_payments` - Pagamenti gruppo
- `btr_payment_links` - Link pagamento condivisi

### Sistema Migrazione
Auto-installer con versionamento per aggiornamenti incrementali database.

## Assets e Dipendenze

### JavaScript
- jQuery per compatibilità WordPress
- Vue.js per componenti reattivi (booking form)
- Dipendenze caricate via WordPress enqueue system

### CSS
- Design system unificato (`btr-unified-design-system.css`)
- Stili specifici per payment, checkout, datepicker
- Override Salient per coerenza visiva

## Aree Non Implementate (TBD)

- **Noleggi**: Sistema noleggio moto/accessori
- **Lezioni**: Prenotazione lezioni guida
- **REST API**: Endpoint custom per app mobile
- **Store API WooCommerce**: Extension per checkout blocks
- **Multi-lingua**: WPML integration completa

## Note Deployment

Build system presente per creazione pacchetti distribuzione. Sistema di versionamento semantico con changelog dettagliato. Debug mode configurabile via `BTR_DEBUG` constant.