# Test Completo del Flusso di Pagamento

## Overview
Il sistema di pagamento di gruppo è stato completamente integrato nel flusso principale del plugin Born to Ride Booking. Questo documento descrive come testare l'intero flusso.

## URL dei Test
- **Test Completo**: `http://born-to-ride.local/test-complete-payment-flow.php`
- **Test Routing**: `http://born-to-ride.local/test-payment-routing.php`
- **Test Generazione Link**: `http://born-to-ride.local/test-group-payment-flow.php`

## Stato Attuale dell'Implementazione

### ✅ Componenti Completati
1. **WordPress Routing** - Configurato per gestire URL `/pay-individual/{hash}/`
2. **Rewrite Rules Manager** - Sistema di gestione versioni per le rewrite rules
3. **BTR_Group_Payments Integration** - Integrato nel flusso principale tramite shortcode
4. **Payment Selection Shortcode** - Modificato per generare link quando si seleziona pagamento di gruppo
5. **Payment Links Summary Template** - Creato per visualizzare i link generati
6. **Admin Interface** - Vista completa per gestire i pagamenti di gruppo

### 🔄 Test da Eseguire

#### 1. Test Configurazione Sistema
1. Accedi a: `http://born-to-ride.local/test-complete-payment-flow.php`
2. Verifica che tutti i check siano verdi:
   - Rewrite Rules configurate ✅
   - Gestione pagamenti gruppo ✅
   - Sistema piani pagamento ✅
   - Shortcode selezione ✅
   - Tabella pagamenti ✅
   - Tabella link ✅
   - Pagina riepilogo creata ✅

#### 2. Test Flusso Completo
1. **Crea Preventivo Test**
   - Dal test page, clicca "Crea Preventivo Test"
   - Verrà creato un preventivo con 4 partecipanti e totale €1200

2. **Compila Anagrafici**
   - Clicca "Vai agli Anagrafici"
   - Compila i dati per tutti i partecipanti
   - Assicurati di inserire email valide per almeno alcuni partecipanti

3. **Seleziona Pagamento di Gruppo**
   - Dopo aver salvato gli anagrafici, verrai reindirizzato alla selezione piano
   - Scegli "Pagamento di Gruppo"
   - Seleziona quali partecipanti pagheranno
   - Specifica quante quote per ciascuno

4. **Verifica Link Generati**
   - Dopo aver cliccato "Crea Piano di Pagamento"
   - Verrai reindirizzato a `/payment-links-summary/`
   - Dovresti vedere una tabella con tutti i link generati

5. **Test Pagamento Individuale**
   - Copia uno dei link generati
   - Aprilo in una finestra incognito
   - Dovresti vedere la pagina di pagamento individuale

## Verifica Componenti

### Database
Le seguenti tabelle devono esistere:
```sql
-- Tabella pagamenti gruppo
wp_btr_group_payments
- payment_id (INT PRIMARY KEY)
- preventivo_id (INT)
- participant_index (INT)
- participant_name (VARCHAR)
- participant_email (VARCHAR)
- amount (DECIMAL)
- payment_status (ENUM)
- created_at (DATETIME)
- updated_at (DATETIME)

-- Tabella link pagamento
wp_btr_payment_links
- link_id (INT PRIMARY KEY)
- payment_id (INT)
- link_hash (VARCHAR)
- is_active (BOOLEAN)
- created_at (DATETIME)
- expires_at (DATETIME)
- used_at (DATETIME)
```

### Rewrite Rules
Verifica che le rewrite rules siano registrate:
1. Vai su: Strumenti > Site Health > Info > WordPress Constants
2. Cerca le rewrite rules
3. Dovresti vedere: `^pay-individual/([a-f0-9]{64})/?`

### Session Data
Durante il test, verifica i dati di sessione WooCommerce:
- `_preventivo_id` - ID del preventivo corrente
- `btr_payment_plan_type` - Tipo di piano selezionato
- `btr_group_participants` - Array dei partecipanti selezionati
- `btr_payment_links` - Link generati

## Troubleshooting

### Rewrite Rules Non Funzionanti
1. Vai su Impostazioni > Permalink
2. Clicca "Salva modifiche" (senza cambiare nulla)
3. Oppure usa il pulsante "Flush Rewrite Rules" nel test

### Link Non Generati
1. Verifica che BTR_Group_Payments sia attiva
2. Controlla il debug log per errori
3. Assicurati che i partecipanti abbiano email valide

### Email Non Inviate
1. Verifica configurazione SMTP
2. Controlla opzione `btr_auto_send_payment_links`
3. Guarda i log email

## Admin Management

### Visualizzare Pagamenti di Gruppo
1. Vai su: Preventivi > Gestione Preventivi
2. Trova un preventivo con pagamenti di gruppo
3. Clicca "Gestisci Pagamenti di Gruppo"

### Funzionalità Admin
- Visualizza stato di ogni pagamento
- Rigenera link scaduti
- Invia email manualmente
- Monitora statistiche pagamenti

## Prossimi Passi

### Da Implementare
- [ ] QR Code reali per i link di pagamento
- [ ] Dashboard con grafici statistiche
- [ ] Sistema promemoria automatici
- [ ] Export CSV dei pagamenti
- [ ] Integrazione SMS

### Testing Necessari
- [ ] Test con pagamenti reali (sandbox Stripe/PayPal)
- [ ] Test scadenza link dopo 72 ore
- [ ] Test email con diversi provider
- [ ] Test performance con molti partecipanti
- [ ] Test su mobile

## Note Importanti

1. **Ambiente Local**: Assicurati che Local by Flywheel sia in esecuzione
2. **Email**: In ambiente locale le email potrebbero non essere inviate realmente
3. **SSL**: I link di pagamento dovrebbero usare HTTPS in produzione
4. **Cache**: Svuota la cache se non vedi le modifiche