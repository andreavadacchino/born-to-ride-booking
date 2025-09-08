# BTR Sistema Completo di Monitoring e Trace v3.0 - Documentazione Implementazione

## Panoramica Sistema

Il sistema di monitoring BTR v3.0 fornisce **monitoraggio completo delle performance**, **error tracking intelligente**, **user journey tracking** e **system health monitoring** con dashboard real-time e sistema di alerting avanzato.

### Componenti Principali

1. **BTR_Monitor** - Core del sistema di monitoring
2. **BTR_Monitor_Integration** - Integrazione con componenti esistenti
3. **BTR_Monitor_Alerts** - Sistema avanzato di alerting e notifiche
4. **Dashboard Real-time** - Interfaccia admin con metriche live
5. **Export System** - Export dati in formato JSON, CSV, PDF

## Architettura Sistema

### Database Schema

Il sistema crea 4 tabelle dedicate:

```sql
-- Tabella metriche principali
wp_btr_monitor_metrics (
    id, session_id, user_id, metric_type, metric_name, 
    metric_value, metric_unit, component, request_uri, 
    user_agent, metadata, timestamp
)

-- Tabella errori e exceptions
wp_btr_monitor_errors (
    id, session_id, user_id, error_type, error_level,
    error_message, error_file, error_line, stack_trace,
    component, request_uri, user_agent, resolved, timestamp
)

-- Tabella user journey
wp_btr_monitor_journey (
    id, session_id, user_id, journey_step, journey_stage,
    step_data, conversion_funnel, drop_off, duration_ms,
    request_uri, referrer, timestamp
)

-- Tabella system health
wp_btr_monitor_health (
    id, component, health_check, status, response_time_ms,
    error_message, metadata, timestamp
)
```

### Configurazione

Il sistema si configura tramite opzioni WordPress:

```php
// Configurazione principale
btr_monitor_enabled = true
btr_monitor_performance = true
btr_monitor_errors = true
btr_monitor_journey = true
btr_monitor_health = true
btr_monitor_dashboard = true

// Configurazione retention e performance
btr_monitor_retention = 30 // giorni
btr_monitor_sample_rate = 100 // 100% tracking
btr_monitor_cache_ttl = 300 // 5 minuti

// Soglie alert
btr_alert_response_time_threshold = 5000 // ms
btr_alert_error_rate_threshold = 5 // %
btr_alert_memory_threshold = 256 // MB
```

## Metriche Tracciabili

### 1. Performance Monitoring

**Metriche Core:**
- `request_duration` - Tempo di risposta richieste (ms)
- `peak_memory` - Utilizzo memoria di picco (MB)
- `memory_growth` - Crescita memoria durante richiesta (MB)
- `ajax_duration` - Tempo risposta AJAX (ms)
- `slow_query` - Query database lente >100ms

**Metriche Componenti:**
- `calculator.operation_duration` - Tempo calcoli pricing
- `preventivi.creation_duration` - Tempo creazione preventivi
- `payment.process_duration` - Tempo elaborazione pagamenti
- `gateway.call_duration` - Tempo chiamate gateway
- `checkout.process_duration` - Tempo processo checkout

### 2. Error Tracking

**Livelli Errore:**
- `critical` - Errori fatali, eccezioni critiche
- `error` - Errori standard, failure operazioni
- `warning` - Warning, operazioni degraded
- `notice` - Notice, informazioni debug

**Tipi Errore:**
- `php_error` - Errori PHP runtime
- `exception` - Eccezioni non gestite
- `wp_die` - WordPress die/fatal errors
- `ajax_error` - Errori nelle chiamate AJAX
- `alert` - Alert sistema monitoring

### 3. User Journey Tracking

**Stages del Journey:**
- `initialization` - Inizio sessione utente
- `shopping` - Navigazione prodotti/pacchetti
- `calculation` - Utilizzo calcolatore prezzi
- `quote_generation` - Generazione preventivo
- `checkout` - Processo di checkout
- `payment_processing` - Elaborazione pagamento
- `conversion` - Completamento ordine
- `fulfillment` - Post-vendita

**Funnel di Conversione:**
- `booking` - Funnel prenotazione principale
- `quote_to_order` - Conversione preventivo → ordine
- `payment_completion` - Completamento pagamento

### 4. System Health Monitoring

**Componenti Monitorati:**
- `database` - Connettività e performance DB
- `woocommerce` - Stato e funzionalità WC
- `btr_plugin` - Integrità plugin BTR
- `memory` - Utilizzo memoria sistema
- `disk_space` - Spazio disco disponibile
- `external_services` - Servizi esterni e API

**Health Status:**
- `healthy` - Componente funzionante
- `warning` - Performance degradate
- `critical` - Malfunzionamenti gravi

## Integrazione con Componenti Esistenti

### 1. Unified Calculator Integration

```php
// Tracking automatico operazioni calcolo
do_action('btr_calculator_start', $operation, $input_data);
do_action('btr_calculator_complete', $operation, $input_data, $result);
do_action('btr_calculator_error', $operation, $error_message);
```

### 2. Sistema Preventivi Integration

```php
// Tracking creazione preventivi
do_action('btr_preventivo_creation_start');
do_action('btr_preventivo_creation_complete', $preventivo_id, $data);
do_action('btr_preventivo_creation_error', $error, $data);
```

### 3. Sistema Pagamenti Integration

```php
// Tracking elaborazione pagamenti
do_action('btr_payment_process_start', $type, $amount);
do_action('btr_payment_process_complete', $type, $amount, $transaction_id);
do_action('btr_payment_process_error', $type, $error_message);

// Tracking chiamate gateway
do_action('btr_gateway_call_start', $gateway, $operation);
do_action('btr_gateway_call_complete', $gateway, $operation, $response);
do_action('btr_gateway_call_error', $gateway, $error_message);
```

### 4. Checkout System Integration

```php
// Tracking processo checkout
do_action('btr_checkout_start');
do_action('btr_checkout_step', $step, $data);
do_action('btr_checkout_complete', $order_id, $order_data);
do_action('btr_checkout_error', $step, $error_message);
```

## Sistema di Alerting

### Regole di Alert Predefinite

1. **High Error Rate** - Tasso errori >5% in 1 ora
2. **Critical Errors** - Presenza errori critici (immediato)
3. **Slow Response Time** - Tempo risposta >5000ms (30 min)
4. **High Memory Usage** - Memoria >256MB (15 min)
5. **Payment Failures** - 3+ failure pagamenti (1 ora)
6. **Database Connectivity** - Problemi DB (5 min)
7. **Low Conversion Rate** - Conversioni <2% (6 ore)
8. **Calculator Errors** - 5+ errori calcolo (1 ora)

### Canali di Notifica

**Email Notifications:**
- Recipients configurabili
- Template HTML responsive
- Dettagli completi alert con link dashboard

**Admin Notices:**
- Notice WordPress admin dismissibili
- Colori basati su severità
- Possibilità acknowledge

**Webhook Integration:**
- POST JSON a endpoint personalizzabile
- Retry automatici
- Timeout configurabile

**Slack Integration:**
- Webhook Slack con attachments colorati
- Canale e username personalizzabili
- Rich formatting con dettagli

**SMS Alerts** (opzionale):
- Integrazione provider SMS (Twilio, etc.)
- Solo per alert critici
- Recipients multipli

### Escalation Automatica

- **Warning → Critical** dopo 60 minuti
- **Critical → Emergency** dopo 30 minuti
- **Emergency** attiva canali aggiuntivi + shutdown

## Dashboard Real-time

### Performance Panel
- Average Response Time con grafico real-time
- Peak Memory Usage
- Cache Hit Rate
- Performance metrics live

### Errors Panel
- Error Rate attuale
- Critical Errors count
- Recent errors list con dettagli
- Error trends

### User Journey Panel
- Conversion funnel con step rates
- Session tracking
- Drop-off points identification
- Journey duration metrics

### System Health Panel
- Component status indicators
- Health check results
- Manual health check trigger
- Response time monitoring

### Activity Log
- Real-time event stream
- Filtri per tipo evento
- Auto-scroll e pause
- Export eventi

### Controlli Dashboard
- Refresh automatico (30s)
- Selezione timeframe (1h, 6h, 24h, 7d)
- Export metrics (JSON, CSV, PDF)
- Page visibility API integration

## Export System

### Formati Supportati

**JSON Export:**
- Struttura completa dati
- Metadata export
- Pretty printing
- Unicode support

**CSV Export:**
- Multi-section CSV
- Headers informativi
- Separatori sezioni
- Excel compatibility

**PDF Export:**
- Report professionale TCPDF
- Executive summary
- Tabelle dettagliate
- Grafici e statistiche
- Branding personalizzabile

### Dati Esportabili

- **Metrics** - Tutte le metriche performance/business
- **Errors** - Log errori completo con stack traces
- **Journey** - Percorsi utente e conversion funnel
- **Health** - Storico health checks componenti
- **Export Info** - Metadata export per audit

## API e Hook per Sviluppatori

### Hook di Monitoring

```php
// Performance tracking
do_action('btr_monitor_track_metric', $type, $name, $value, $unit, $component);

// Error tracking
do_action('btr_monitor_track_error', $type, $level, $message, $file, $line);

// Journey tracking
do_action('btr_monitor_track_journey', $step, $stage, $data, $funnel);

// Health monitoring
do_action('btr_monitor_health_check', $component, $status, $data);
```

### Filtri di Configurazione

```php
// Alert rules customization
apply_filters('btr_monitor_alert_rules', $rules);

// Notification channels
apply_filters('btr_monitor_notification_channels', $channels);

// Escalation rules
apply_filters('btr_monitor_escalation_rules', $rules);

// Export data transformation
apply_filters('btr_monitor_export_data', $data, $timeframe);
```

### Metodi API Pubblici

```php
$monitor = BTR_Monitor::get_instance();

// Manual metrics recording
$monitor->record_metric($type, $name, $value, $unit, $component, $metadata);

// Timer management
$monitor->start_timer($name);
$duration = $monitor->end_timer($name);

// Journey tracking
$monitor->track_journey_step($step, $stage, $data, $funnel);

// Health checks
$health_results = $monitor->run_health_checks();

// Dashboard data
$dashboard_data = $monitor->get_dashboard_metrics($timeframe);
```

## Ottimizzazione Performance

### Sampling Strategy
- Sample rate configurabile (default 100%)
- Sampling intelligente per operazioni costose
- Bypass sampling per errori critici

### Caching Strategy
- Transient caching per dashboard data (5 min)
- Recent metrics cache per real-time display
- Query result caching per export

### Database Optimization
- Indici ottimizzati per query frequenti
- Cleanup automatico dati vecchi (retention policy)
- Query batching per inserimenti multipli

### Memory Management
- Memory snapshots strategici
- Cleanup automatico variabili temporanee
- Limite memoria configurabile per alert

## Sicurezza e Privacy

### Access Control
- Capabilities WordPress (`manage_options`)
- Nonce verification per tutti AJAX
- Sanitizzazione input utente

### Data Privacy
- Hashing session IDs sensibili
- Troncamento URL lunghi
- Exclusion dati sensibili dai log

### Error Handling
- Graceful degradation se monitoring fallisce
- Fallback logging su file system
- Non-blocking error tracking

## Manutenzione e Troubleshooting

### Cleanup Automatico
- Cron job giornaliero per cleanup dati vecchi
- Retention policy configurabile per tipo dato
- Pulizia transient cache scaduti

### Diagnostica
- Health endpoint per monitoring esterno
- Debug logging dettagliato
- Self-monitoring del monitoring system

### Recovery Procedures
- Ricostruzione tabelle database corrotte
- Reset configurazione ai default
- Emergency disable monitoring

## Roadmap Futuri Sviluppi

### v3.1 - Estensioni
- Machine learning per anomaly detection
- Predictive alerting basato su patterns
- Custom dashboard widgets

### v3.2 - Integrations
- Integrazione APM tools (New Relic, DataDog)
- Slack bot interattivo
- Mobile app notifiche

### v3.3 - Advanced Analytics
- Performance regression detection
- User behavior analytics
- Business intelligence dashboard

---

**Documentazione Tecnica**: v1.0.216 | **Data**: Agosto 2025 | **Autore**: Claude Code SuperClaude