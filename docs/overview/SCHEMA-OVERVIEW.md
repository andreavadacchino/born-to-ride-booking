# Born to Ride - Schema Dati Overview

## Panoramica Persistenza

Il sistema utilizza un approccio ibrido per la persistenza dei dati, combinando strutture WordPress native con tabelle custom per ottimizzare performance e relazioni complesse.

## Entità Business Principali

### 1. **Pacchetti Viaggio** 
**Persistenza**: Post Type `btr_pacchetti` + Post Meta estensivi  
**Concetto**: Template di viaggio con configurazioni base (durata, prezzi, disponibilità)  
**Relazioni**: Collegato a WooCommerce Product variabile per e-commerce, contiene Date Ranges multiple, definisce Extra Costs e Insurances disponibili

### 2. **Date e Disponibilità**
**Persistenza**: Tabella `btr_date_ranges` + Post Meta `btr_date_ranges`  
**Concetto**: Periodi prenotabili con capienza, prezzi stagionali, stato aperto/chiuso  
**Relazioni**: Appartengono a Pacchetti, generano Variazioni WooCommerce, influenzano Room Allotments

### 3. **Preventivi/Quote**
**Persistenza**: Post Type `preventivi` + Tabella `btr_quotes` (in evoluzione)  
**Concetto**: Configurazione salvata di un viaggio con partecipanti, camere, costi  
**Relazioni**: Riferisce un Pacchetto, contiene Partecipanti multipli, può diventare Ordine WooCommerce, traccia Extra Costs selezionati

### 4. **Partecipanti**
**Persistenza**: Tabella `btr_quote_participants` + Session temporanea  
**Concetto**: Persone nel viaggio con anagrafica, età, assegnazione camera  
**Relazioni**: Appartengono a Quote, categorizzati per età (adult/child/infant), influenzano prezzi tramite Child Categories

### 5. **Categorie Età Bambini**
**Persistenza**: Tabella `btr_child_categories` + `btr_child_prices`  
**Concetto**: Fasce età dinamiche (f1:3-6, f2:6-8, f3:8-10, f4:11-12) con sconti percentuali  
**Relazioni**: Definite per Pacchetto, applicate a Partecipanti, modificano calcoli prezzi base

### 6. **Camere e Allotment**
**Persistenza**: Tabelle `btr_package_rooms`, `btr_room_allotments`, `btr_quote_rooms`  
**Concetto**: Tipologie camera disponibili con capienza e supplementi  
**Relazioni**: Configurate per Pacchetto, assegnate in Quote, limitano disponibilità per Date Range

### 7. **Costi Extra**
**Persistenza**: Tabella `btr_extra_costs` + `btr_quote_extra_costs`  
**Concetto**: Servizi aggiuntivi opzionali (skipass, transfer, noleggi)  
**Relazioni**: Definiti per Pacchetto, selezionabili in Quote, calcolati per persona o per notte

### 8. **Assicurazioni**
**Persistenza**: Tabella `btr_insurances` + `btr_quote_insurances`  
**Concetto**: Polizze viaggio con coperture e prezzi percentuali o fissi  
**Relazioni**: Offerte per Pacchetto, applicabili a Quote/Partecipanti

### 9. **Pagamenti di Gruppo**
**Persistenza**: Tabelle `btr_group_payments`, `btr_order_shares`, `btr_payment_links`  
**Concetto**: Divisione pagamento tra partecipanti con link individuali  
**Relazioni**: Collegato a Ordini WooCommerce, traccia quote per Partecipante, genera Payment Links temporanei

### 10. **Piani Pagamento** (in evoluzione)
**Persistenza**: Tabella `btr_payment_plans` + `btr_payment_reminders`  
**Concetto**: Rateizzazione con scadenze e reminder automatici  
**Relazioni**: Applicabile a Ordini, schedulazione tramite Cron

## Dati Temporanei e Session

### In Memoria/Session
- **Form Multi-Step State**: WooCommerce Session durante compilazione
- **Calcoli Prezzi Runtime**: Non persistiti, ricalcolati al volo
- **Configurazione Preventivo Temp**: Session fino a salvataggio

### Transient/Cache
- **Token Sicurezza**: Transient per link pagamento (scadenza 7gg)
- **Cache Disponibilità**: Transient per query date frequenti
- **Webhook Queue**: Tabella `btr_webhook_dlq` per retry

## Flussi Dati Principali

### Creazione Preventivo
1. **Selezione Pacchetto** → legge Post Meta configurazione
2. **Scelta Date** → query `btr_date_ranges` per disponibilità  
3. **Partecipanti** → salva in Session temporanea
4. **Anagrafici** → persiste in `btr_quote_participants`
5. **Conversione Ordine** → crea WooCommerce Order + meta

### Sincronizzazione WooCommerce
- **Pacchetti → Products**: Automatica via hook su save
- **Date Ranges → Variations**: Generate per attributi date/camere
- **Quote → Cart Items**: Custom fields in sessione cart
- **Orders → Quote Status**: Webhook bidirezionale

## Aree in Evoluzione

### Moduli Futuri (TBD)
- **Noleggi**: Struttura tabelle pronta, logica non implementata
- **Lezioni**: Placeholder in schema, sviluppo pendente
- **Multi-lingua**: Meta fields predisposti, WPML integration WIP

### Refactoring in Corso
- **Calculator Unificato**: Migrazione da calcoli duplicati frontend/backend a single source
- **Quote Storage**: Transizione da Post Type a tabella custom completa
- **Session Management**: Valutazione alternative a WooCommerce Session per scalabilità

### Ottimizzazioni Pianificate
- **Indicizzazione**: Ottimizzazione query su date ranges e disponibilità
- **Caching Layer**: Redis/Memcached per calcoli prezzi frequenti
- **Event Sourcing**: Log modifiche quote per audit trail completo

## Note Tecniche

**Prefisso Tabelle**: `KrUHSqSf_` (Local by Flywheel)  
**Charset**: `utf8mb4_unicode_520_ci`  
**Storage Engine**: InnoDB con foreign keys dove applicabile  
**Versioning**: Schema version tracking per migrations incrementali