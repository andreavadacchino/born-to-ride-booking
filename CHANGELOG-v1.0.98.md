# Changelog v1.0.98 - Sistema di Pagamenti di Gruppo

## [1.0.98] - 2025-01-21

### 🎯 Overview
Implementazione completa di un sistema di pagamenti di gruppo che permette ai partecipanti di pagare individualmente le proprie quote di viaggio attraverso link personalizzati e sicuri.

### ✨ Nuove Funzionalità

#### Sistema di Pagamenti Esteso
- **Tre modalità di pagamento**:
  - **Pagamento Completo**: Pagamento standard in un'unica soluzione
  - **Caparra + Saldo**: Pagamento con caparra configurabile (10-90%) e saldo successivo
  - **Suddivisione Gruppo**: Ogni partecipante paga la propria quota individualmente

#### Gestione Quote Individuali
- Generazione automatica di link di pagamento individuali sicuri (hash SHA256)
- Pagine di checkout dedicate per ogni partecipante
- Tracking real-time dei pagamenti completati
- Visualizzazione progressi di pagamento del gruppo

#### Sistema di Notifiche
- Email automatiche con template personalizzabili
- Notifiche per: link pagamento, conferma pagamento, reminder, scadenza
- Cron job per reminder automatici configurabili
- Template email responsive con supporto multilingua

#### Interfaccia Amministrativa
- Dashboard completa per monitoraggio pagamenti
- Filtri avanzati per stato, data, piano, partecipante
- Azioni bulk per invio reminder
- Export CSV con tutti i dati
- Statistiche real-time con grafici progressi

#### Sicurezza e Validazione
- Validazione completa di tutti i dati input
- Rate limiting per prevenire abusi
- CSRF protection su tutte le operazioni
- Encryption dei dati sensibili
- Hash sicuri per link di pagamento
- Logging di sicurezza per audit

### 🔧 Dettagli Tecnici

#### Database
- **Nuove tabelle**:
  - `wp_btr_payment_plans`: Piani di pagamento per preventivo
  - `wp_btr_payment_reminders`: Sistema reminder automatici
- **Tabelle estese**:
  - `wp_btr_group_payments`: Nuovi campi per gestione estesa

#### File Aggiunti

**Core Classes**:
- `includes/class-btr-payment-plans.php` - Logica principale piani di pagamento
- `includes/class-btr-payment-email-manager.php` - Gestione email e notifiche
- `includes/class-btr-payment-cron.php` - Cron job per reminder automatici
- `includes/class-btr-payment-security.php` - Sicurezza e validazione
- `includes/class-btr-payment-ajax.php` - Handler AJAX
- `includes/class-btr-payment-rewrite.php` - Rewrite rules per URL
- `includes/class-btr-payment-integration.php` - Integrazione con plugin principale

**Admin**:
- `admin/class-btr-payment-plans-admin.php` - Interfaccia amministrativa
- `admin/views/payment-plans-admin.php` - Vista tabella pagamenti
- `admin/js/payment-plans-admin.js` - JavaScript amministrazione
- `admin/css/payment-plans-admin.css` - Stili amministrazione

**Frontend**:
- `templates/frontend/payment-plan-selection.php` - Modal selezione piano
- `templates/frontend/checkout-group-payment.php` - Pagina checkout individuale
- `assets/js/payment-integration.js` - JavaScript integrazione frontend

#### URL e Routing
- `/pagamento-gruppo/{hash}` - Checkout individuale
- `/pagamento-confermato/{hash}` - Pagina conferma
- `/stato-pagamento/{hash}` - API stato pagamento

#### Hook e Filtri

**Actions**:
- `btr_payment_plan_created` - Dopo creazione piano
- `btr_payment_completed` - Dopo pagamento completato
- `btr_group_payment_completed` - Quando tutti hanno pagato
- `btr_payment_expired` - Quando link scade
- `btr_send_payment_reminder` - Per invio reminder

**Filters**:
- `btr_payment_email_content` - Modifica contenuto email
- `btr_checkout_redirect_url` - Modifica redirect checkout
- `btr_payment_link_expiry_days` - Giorni validità link

### 📋 Configurazione

#### Impostazioni Admin
- **Percentuale Caparra Default**: 10-90% (default: 30%)
- **Giorni Scadenza Link**: 1-365 giorni (default: 30)
- **Reminder Automatici**: On/Off
- **Giorni Anticipo Reminder**: 1-30 giorni (default: 7)

#### Requisiti
- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.2+
- MySQL 5.6+

### 🔐 Sicurezza

- Tutti i link di pagamento usano hash SHA256 univoci
- Validazione completa lato server di tutti gli input
- Rate limiting: max 10 richieste/ora per IP
- CSRF token su tutte le operazioni critiche
- Encryption AES-256 per dati sensibili
- Logging completo per audit trail

### 📝 Note per gli Sviluppatori

#### Estendere il Sistema
Il sistema è progettato per essere estensibile attraverso hook e filtri. Esempi:

```php
// Aggiungere un nuovo tipo di piano
add_filter('btr_payment_plan_types', function($types) {
    $types['installments'] = __('Pagamento Rateale', 'born-to-ride-booking');
    return $types;
});

// Modificare template email
add_filter('btr_payment_email_content', function($content, $template, $data) {
    // Modifica contenuto
    return $content;
}, 10, 3);
```

#### Testing
- Test manuale checkout: Creare preventivo → Selezionare piano gruppo → Testare link
- Test cron: `wp cron event run btr_payment_reminders_cron`
- Test sicurezza: Verificare rate limiting e validazione

### 🐛 Bug Fix dalla v1.0.97
- Nessun bug fix in questa versione (focus su nuove funzionalità)

### ⚠️ Breaking Changes
- Nessuno - Completamente retrocompatibile

### 🚀 Upgrade Notes
1. Backup database prima dell'aggiornamento
2. L'aggiornamento creerà automaticamente le nuove tabelle
3. Flush rewrite rules dopo attivazione
4. Configurare impostazioni in Admin → Born to Ride → Impostazioni Pagamenti

### 📈 Performance
- Query ottimizzate con indici appropriati
- Caching dei risultati frequenti
- Lazy loading per statistiche admin
- Batch processing per operazioni bulk