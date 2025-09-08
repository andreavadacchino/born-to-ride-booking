# 🤖 Database Auto-Installer - Born to Ride Booking

## Panoramica

Il sistema **Database Auto-Installer** gestisce automaticamente la creazione e l'aggiornamento delle tabelle del database per il plugin Born to Ride Booking. Non è più necessario eseguire manualmente script di update!

## 🚀 Caratteristiche Principali

### Auto-Installazione
- **Creazione automatica** delle tabelle al primo utilizzo
- **Aggiornamento incrementale** quando la versione cambia
- **Verifica continua** dell'integrità del database
- **Recupero automatico** in caso di tabelle mancanti

### Tabelle Gestite
1. `wp_btr_payment_plans` - Piani di pagamento
2. `wp_btr_group_payments` - Pagamenti di gruppo
3. `wp_btr_payment_reminders` - Promemoria pagamenti

### Pagine WordPress
Crea automaticamente:
- Checkout Caparra (`[btr_checkout_deposit]`)
- Riepilogo Pagamento Gruppo (`[btr_group_payment_summary]`)
- Conferma Prenotazione (`[btr_booking_confirmation]`)

## 📋 Come Funziona

### 1. Attivazione Automatica
Il sistema si attiva automaticamente in questi momenti:
- **Al caricamento del plugin** (`plugins_loaded`)
- **All'accesso all'admin** (`admin_init`)
- **All'attivazione del plugin**

### 2. Processo di Verifica
```php
// Il sistema verifica automaticamente:
1. Esistenza di tutte le tabelle richieste
2. Versione del database corrente
3. Necessità di aggiornamenti
4. Creazione/update se necessario
```

### 3. Nessun Intervento Richiesto
**Prima (vecchio sistema):**
```
1. Visitare /tests/test-database-updater.php
2. Cliccare "Esegui Update"
3. Verificare manualmente
```

**Ora (auto-installer):**
```
✅ Tutto automatico!
```

## 🔧 Utilizzo nel Codice

### Verifica Stato Database
```php
// Ottieni istanza
$installer = BTR_Database_Auto_Installer::get_instance();

// Verifica stato
$status = $installer->get_database_status();

if (!$status['is_ready']) {
    // Le tabelle verranno create automaticamente
    echo "Database in fase di installazione...";
}
```

### Struttura Status
```php
$status = [
    'version' => '1.0.98',           // Versione corrente
    'target_version' => '1.0.98',    // Versione target
    'is_ready' => true,              // Sistema pronto?
    'tables' => [                    // Stato tabelle
        'btr_payment_plans' => [
            'exists' => true,
            'name' => 'wp_btr_payment_plans'
        ],
        // ... altre tabelle
    ],
    'pages' => [                     // Stato pagine
        'checkout-caparra' => [
            'exists' => true,
            'id' => 123
        ],
        // ... altre pagine
    ]
];
```

## 🛠️ Testing e Debug

### Pagina di Test
Visita: `/wp-content/plugins/born-to-ride-booking/tests/test-auto-installer.php`

Questa pagina mostra:
- ✅ Stato di tutte le tabelle
- ✅ Stato delle pagine WordPress
- ✅ Versione del database
- ✅ Log di installazione
- ✅ Opzioni per forzare reinstallazione (debug)

### Log di Sistema
```php
// I log sono salvati in:
get_option('btr_db_installation_log');

// Esempio log:
[
    'timestamp' => '2025-01-21 20:30:00',
    'type' => 'success',
    'message' => 'Installazione completata con successo',
    'version' => '1.0.98'
]
```

## 🔄 Aggiornamenti Futuri

### Aggiungere Nuove Tabelle
1. Modifica il metodo `get_[table]_schema()` in `class-btr-database-auto-installer.php`
2. Incrementa la costante `DB_VERSION`
3. Il sistema aggiornerà automaticamente al prossimo caricamento

### Modificare Colonne Esistenti
1. Aggiungi logica in `add_missing_columns()`
2. Incrementa `DB_VERSION`
3. Le modifiche saranno applicate automaticamente

## ⚠️ Note Importanti

### Sicurezza
- Le tabelle **NON** vengono rimosse alla disattivazione del plugin
- Solo gli admin possono forzare reinstallazioni
- Tutti gli update sono transazionali

### Performance
- Verifica rapida ad ogni caricamento (< 50ms)
- Installazione solo quando necessario
- Nessun impatto sulle performance in produzione

### Compatibilità
- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+ / MariaDB 10.0+

## 🐛 Troubleshooting

### Le tabelle non si creano
1. Verifica permessi MySQL dell'utente WordPress
2. Controlla `wp-content/debug.log` per errori
3. Usa la pagina di test per forzare reinstallazione

### Errore "Table already exists"
Normale durante aggiornamenti. Il sistema usa `CREATE TABLE IF NOT EXISTS`.

### Performance lenta all'avvio
Possibile solo al primo avvio. Le verifiche successive sono cachate.

## 📝 Changelog

### v1.0.98 (21/01/2025)
- ✅ Implementato sistema di auto-installazione
- ✅ Aggiunto supporto per aggiornamenti incrementali
- ✅ Creazione automatica pagine WordPress
- ✅ Sistema di logging integrato
- ✅ Pagina di test e debug

## 🎯 Vantaggi

1. **Zero Manutenzione**: Non serve più eseguire update manuali
2. **Affidabilità**: Recovery automatico in caso di problemi
3. **Tracciabilità**: Log completi di tutte le operazioni
4. **Semplicità**: Nessuna configurazione richiesta
5. **Sicurezza**: Validazioni e controlli integrati

---

**Il Database Auto-Installer rende il sistema pagamenti Born to Ride completamente autonomo e pronto all'uso!** 🚀