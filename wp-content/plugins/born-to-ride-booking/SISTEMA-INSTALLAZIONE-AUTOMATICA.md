# Sistema di Installazione Automatica - Born to Ride Booking

## Panoramica

Il plugin Born to Ride Booking **installa automaticamente** sia il database che le pagine necessarie quando viene attivato in produzione. Non è necessario alcun intervento manuale.

## 1. Installazione Database - AUTOMATICA ✅

### Sistema Multi-Livello

Il plugin utilizza un sistema a 3 livelli per garantire l'installazione del database:

#### Livello 1: Hook di Attivazione
- **File**: `includes/class-btr-database-auto-installer.php`
- **Hook**: `register_activation_hook(BTR_PLUGIN_FILE, [$this, 'install_tables'])`
- **Quando**: All'attivazione del plugin
- **Cosa fa**: Crea tutte le tabelle necessarie

#### Livello 2: Controllo all'Inizializzazione
- **File**: `born-to-ride-booking.php` (riga 382-386)
- **Metodo**: `maybe_create_tables()`
- **Quando**: Ad ogni caricamento del plugin
- **Cosa fa**: Verifica se le tabelle esistono e le crea se mancanti

#### Livello 3: Admin Init
- **File**: `includes/class-btr-database-installer.php`
- **Hook**: `add_action('admin_init', ...)`
- **Quando**: Quando si accede all'admin
- **Cosa fa**: Controllo aggiuntivo e aggiornamenti

### Tabelle Create Automaticamente

1. **btr_payment_plans** - Piani di pagamento
2. **btr_group_payments** - Pagamenti di gruppo
3. **btr_payment_reminders** - Promemoria pagamenti
4. **btr_order_shares** - Quote ordini (da altra classe)
5. **btr_payment_webhook_dlq** - Dead letter queue webhook

### Versioning Database
- Versione corrente: **1.1.0**
- Salvata in: `btr_db_version`
- Sistema di migrazione automatica per aggiornamenti futuri

## 2. Creazione Pagine - AUTOMATICA ✅

### Pagine Create all'Attivazione

Il metodo `create_payment_pages()` crea automaticamente:

1. **Checkout Caparra**
   - Slug: `checkout-caparra`
   - Shortcode: `[btr_checkout_deposit]`
   - Option: `btr_checkout_deposit_page`

2. **Riepilogo Pagamento Gruppo**
   - Slug: `riepilogo-pagamento-gruppo`
   - Shortcode: `[btr_group_payment_summary]`
   - Option: `btr_group_payment_summary_page`

3. **Conferma Prenotazione**
   - Slug: `conferma-prenotazione`
   - Shortcode: `[btr_booking_confirmation]`
   - Option: `btr_booking_confirmation_page`

### Pagina Selezione Pagamento

La pagina **Selezione Piano Pagamento** viene creata:
- **Manualmente**: Dal pannello admin (Impostazioni Pagamenti)
- **Automaticamente**: Tramite pulsante nell'admin
- **Slug**: `selezione-piano-pagamento`
- **Shortcode**: `[btr_payment_selection]`

## 3. Processo di Installazione in Produzione

### Quando Attivi il Plugin:

1. **Immediato**: 
   - Hook `register_activation_hook` si attiva
   - Crea tutte le tabelle del database
   - Crea le 3 pagine principali

2. **Al Primo Caricamento**:
   - `maybe_create_tables()` verifica tutto sia OK
   - Se manca qualcosa, lo crea

3. **Al Primo Accesso Admin**:
   - Controllo finale tramite `admin_init`
   - Log di installazione salvato

### Cosa NON Devi Fare

❌ Creare manualmente le tabelle
❌ Creare manualmente le pagine (tranne quella di selezione pagamento se vuoi)
❌ Modificare il database
❌ Eseguire script SQL

### Cosa PUOI Fare (Opzionale)

✅ Creare la pagina "Selezione Piano Pagamento" dal pannello admin
✅ Verificare lo stato del database dalla pagina di diagnostica
✅ Controllare i log di installazione

## 4. Verifica Post-Installazione

### Controllo Database
```php
// Il plugin fornisce un metodo per verificare
$installer = BTR_Database_Auto_Installer::get_instance();
$status = $installer->get_database_status();
// Mostra versione, tabelle, pagine e stato generale
```

### Controllo Pagine
- Vai su **Pagine** nel menu WordPress
- Dovresti vedere le 3 pagine create automaticamente
- La pagina di selezione pagamento va creata dall'admin

## 5. Troubleshooting

### Se le Tabelle Non Si Creano
1. Verifica i permessi MySQL dell'utente
2. Controlla il prefisso tabelle in `wp-config.php`
3. Guarda i log in: `btr_db_installation_log` (option)

### Se le Pagine Non Si Creano
1. Verifica i permessi di scrittura WordPress
2. Controlla che non esistano già pagine con gli stessi slug
3. L'ID delle pagine è salvato nelle options di WordPress

## 6. Disinstallazione

**IMPORTANTE**: Il plugin NON rimuove le tabelle quando viene disattivato per sicurezza dei dati.

Per rimuovere completamente:
1. Esporta i dati se necessari
2. Disattiva il plugin
3. Elimina manualmente le tabelle dal database (se desiderato)
4. Elimina le pagine create

## Riepilogo

✅ **Database**: Installazione completamente automatica
✅ **Pagine Base**: Create automaticamente all'attivazione
✅ **Pagina Selezione**: Creabile con un click dall'admin
✅ **Aggiornamenti**: Sistema di migrazione automatica
✅ **Sicurezza**: I dati non vengono mai persi accidentalmente

**Non è necessario alcun intervento manuale per l'installazione base del plugin!**