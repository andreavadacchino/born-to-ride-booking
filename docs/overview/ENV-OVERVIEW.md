# Born to Ride - Environment Overview

## Stack Versions

### Core Platform
- **WordPress**: 6.8.1
- **WooCommerce**: 9.9.5
- **PHP**: 8.4.5 (CLI mode, Homebrew build)
- **MySQL**: 8.0.35 (Local by Flywheel)

### Theme
- **Salient**: 17.0.5 (Premium multi-purpose theme da ThemeForest)
  - Include WPBakery Page Builder (ex Visual Composer)
  - Responsive framework con gestione portfolio/slider custom

### Plugin Critici per Booking

**Plugin Proprietario**:
- **Born to Ride Booking**: 1.0.109 (plugin custom per gestione prenotazioni)

**Payment Gateway**:
- **WooCommerce PayPal Payments**: 3.0.7 (supporto PayPal, Pay Later, carte)
- **WooCommerce Stripe Gateway**: 9.6.0 (carte credito/debito, Apple Pay, Google Pay)

**Dipendenze Salient**:
- JS Composer Salient (WPBakery modificato)
- Salient Core, Portfolio, Shortcodes, Nectar Slider
- Tutti necessari per funzionamento completo tema

**Utility Attive**:
- Query Monitor (debugging database e performance)
- WP Migrate DB (sincronizzazione database)
- Performance Lab (ottimizzazioni WordPress core)

## Dipendenze Server

### Estensioni PHP Disponibili
- **Database**: mysqli, PDO con driver MySQL
- **Network**: curl, openssl (per API esterne e gateway pagamento)
- **Processing**: json, mbstring, xml (manipolazione dati)
- **Media**: gd (elaborazione immagini per upload)
- **Archive**: zip (per backup e export)
- **API**: soap (per integrazioni legacy se necessarie)

### Limiti e Risorse

**WordPress Config**:
- Memory Limit: 512M (aumentato da default 128M)
- Max Memory Limit: 768M (per operazioni intensive)
- Debug Mode: Attivo con logging su file
- Debug Log: `wp-content/debug.log`

**PHP Runtime** (sistema):
- memory_limit: 128M (default PHP, override da WP)
- max_execution_time: 0 (unlimited in CLI)
- post_max_size: 8M
- upload_max_filesize: 2M

**Database**:
- Socket connection su filesystem locale
- Charset: utf8 con collation default
- Prefix tabelle: `KrUHSqSf_`

## Ambiente Development

### Local by Flywheel
- **Tipo**: Development locale su macOS
- **MySQL Socket**: `/Users/andreavadacchino/Library/Application Support/Local/run/hFKO0EI1f/mysql/mysqld.sock`
- **URL Base**: `http://localhost:10018`
- **SSL**: Non configurato (ambiente locale)

### Version Control
- **Git Repository**: Presente e attivo
- **Branch Corrente**: `fix/calcoli-extra-notti-2025-01`
- **Tracking**: Modifiche plugin e configurazioni tracciate

### Environment Type
- **WP_ENVIRONMENT_TYPE**: `local` (configurazione development)
- **WP_DEBUG**: `true` con logging attivo
- **Cache**: Non configurata (no Redis/Memcached)

## Staging/Production

### Ambiente Staging
- **Disponibile**: Non rilevato in configurazione locale
- **Best Practice**: Consigliato setup staging su hosting remoto prima di production

### Ambiente Production
- **Status**: Non configurato da ambiente locale
- **Requisiti Minimi Suggeriti**:
  - PHP 8.0+ con OPcache
  - MySQL 8.0+ o MariaDB 10.6+
  - Memory limit minimo 256M
  - SSL certificate per pagamenti
  - CDN per assets statici

## Note Operative

### Backup e Migrazione
- WP Migrate DB installato per export/import database
- Socket MySQL richiede path assoluto per connessioni locali
- Prefix tabelle custom richiede attenzione in migrazione

### Performance Considerations
- No object caching attivo (consigliato Redis in production)
- Debug mode va disattivato in production
- Memory limits generosi per operazioni booking complesse

### Security Notes
- Debug log esposto richiede protezione in production
- Payment gateway in modalità test/sandbox
- SSL obbligatorio per transazioni reali

### Monitoring
- Query Monitor attivo per analisi performance
- Log WordPress per tracking errori
- No APM o monitoring esterno configurato