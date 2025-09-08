# Implementazione Sistema Selezione Pagamento - Born to Ride Booking

## 📅 Data: 21 Gennaio 2025

## 🎯 Obiettivo
Implementare una pagina intermedia tra la compilazione degli anagrafici e il checkout WooCommerce per permettere agli utenti di scegliere il metodo di pagamento, con particolare focus sul pagamento di gruppo flessibile.

## 🔍 Problema Iniziale
Quando l'utente completava gli anagrafici e cliccava "Vai al Checkout", veniva reindirizzato direttamente al checkout WooCommerce senza possibilità di scegliere il metodo di pagamento (completo, caparra, gruppo).

## ✅ Implementazioni Completate

### 1. **Creazione Pagina Selezione Pagamento**

#### File creati:
- `/templates/payment-selection-page.php` - Template principale per la pagina
- `/includes/class-btr-payment-selection-shortcode.php` - Gestione shortcode `[btr_payment_selection]`
- `/admin/payment-settings-page.php` - Configurazione admin per la pagina

#### Funzionalità implementate:
- Progress indicator a 3 step (Anagrafici → Metodo Pagamento → Checkout)
- Riepilogo completo del preventivo con:
  - Dettagli viaggio (date, partecipanti, camere)
  - Breakdown prezzi (base, supplementi, sconti, assicurazioni, extra)
  - Totale partecipanti registrati
- Tre opzioni di pagamento:
  - Pagamento Completo
  - Caparra + Saldo (con slider per percentuale caparra)
  - Pagamento di Gruppo (con selezione partecipanti)

### 2. **Modifica Flusso di Redirect**

#### File modificato:
- `/includes/class-btr-shortcode-anagrafici.php`

#### Modifiche:
```php
// Prima (hardcoded):
'redirect_url' => wc_get_checkout_url()

// Dopo (con logica condizionale):
if ($show_payment_modal) {
    $payment_selection_page_id = get_option('btr_payment_selection_page_id');
    if ($payment_selection_page_id) {
        $redirect_url = add_query_arg('preventivo_id', $preventivo_id, get_permalink($payment_selection_page_id));
    }
}
```

### 3. **Implementazione Pagamento di Gruppo Flessibile**

#### Funzionalità aggiunte:
- Tabella interattiva con tutti i partecipanti adulti
- Checkbox per selezionare chi pagherà
- Input numerico per assegnare multiple quote per partecipante
- Calcolo automatico degli importi basato sulle quote
- Validazione che almeno un partecipante sia selezionato
- Avvisi quando le quote non corrispondono al totale partecipanti

#### Codice JavaScript aggiunto:
- Gestione dinamica selezione partecipanti
- Aggiornamento real-time degli importi
- Validazione pre-submit
- Gestione stati checkbox/input

#### Stili CSS:
- Design tabella professionale
- Animazioni smooth per show/hide
- Layout responsive
- Indicatori visivi per totali e avvisi

### 4. **Backend Processing**

#### Modifiche a `class-btr-payment-selection-shortcode.php`:
```php
// Aggiunto processing per pagamento di gruppo
elseif ($payment_plan === 'group_split') {
    // Processa partecipanti selezionati
    $group_participants = [];
    foreach ($_POST['group_participants'] as $index => $participant) {
        if (isset($participant['selected']) && $participant['selected'] == '1') {
            $group_participants[] = [
                'index' => intval($index),
                'name' => sanitize_text_field($participant['name']),
                'email' => sanitize_email($participant['email']),
                'shares' => intval($participant['shares'])
            ];
        }
    }
    // Salva in sessione WooCommerce
    WC()->session->set('btr_group_participants', $group_participants);
}
```

### 5. **Script di Debug e Test**

#### File creati per testing:
- `create-test-package.php` - Crea pacchetti e preventivi di test
- `test-payment-page-data.php` - Verifica dati nella pagina
- `fix-payment-page-config.php` - Fix configurazione pagina
- `debug-redirect-issue.php` - Debug problemi redirect

## 🚧 Lavoro Ancora da Completare

### 1. **Classe BTR_Group_Payments**
File da creare: `/includes/class-btr-group-payments.php`

Funzionalità necessarie:
- Generazione link di pagamento univoci per ogni partecipante
- Gestione hash sicuri per i link
- Tracking stato pagamenti individuali
- Integrazione con tabelle database esistenti

### 2. **Database Tables**
Tabelle già definite in `class-btr-database.php`:
- `btr_group_payments` - Per tracciare pagamenti individuali
- `btr_payment_links` - Per gestire i link di pagamento

### 3. **Pagina Riepilogo Post-Selezione**
Dopo la selezione del pagamento di gruppo, mostrare:
- Lista partecipanti con importi assegnati
- Link di pagamento generati per ciascuno
- QR code per facilitare la condivisione
- Pulsante per inviare email ai partecipanti

### 4. **Sistema Email**
- Template email per invio link pagamento
- Tracking aperture e click
- Reminder automatici per pagamenti pendenti

### 5. **Gestione Pagamenti Individuali**
- Endpoint `/btr-payment/{hash}` per accesso tramite link
- Creazione prodotto WooCommerce temporaneo con importo custom
- Associazione pagamento al preventivo originale
- Aggiornamento stato dopo pagamento completato

### 6. **Admin Dashboard**
- Vista per monitorare stato pagamenti di gruppo
- Report pagamenti completati/pendenti
- Possibilità di reinviare link
- Export dati pagamenti

### 7. **Hook e Filtri**
Da implementare:
- `btr_before_group_payment_created`
- `btr_after_payment_link_generated`
- `btr_group_payment_completed`
- `btr_all_group_payments_completed`

### 8. **Sicurezza**
- Validazione hash link pagamento
- Scadenza link dopo X giorni
- Rate limiting per prevenire abusi
- Logging accessi ai link

## 🔧 Configurazione Necessaria

### WordPress Admin:
1. Creare pagina "Selezione Metodo di Pagamento"
2. Inserire shortcode `[btr_payment_selection]`
3. Configurare ID pagina in Impostazioni → Born to Ride → Pagamenti

### Testing:
1. Creare pacchetto di test con `create-test-package.php`
2. Compilare anagrafici nel frontend
3. Verificare redirect alla pagina selezione
4. Testare selezione partecipanti per gruppo
5. Verificare dati salvati in sessione

## 📝 Note Tecniche

### Dipendenze:
- WooCommerce attivo e configurato
- Sessioni WooCommerce per mantenere dati tra pagine
- jQuery per interattività frontend

### Compatibilità:
- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.2+

### Performance:
- Caching dati preventivo per ridurre query
- Lazy loading partecipanti se > 50
- Ottimizzazione query per pagamenti di gruppo

## 🐛 Problemi Noti

1. **QR Code**: Attualmente placeholder, necessita libreria QR
2. **Email HTML**: Solo testo plain, necessita template HTML
3. **Prodotto Gruppo**: Necessita gestione migliore prodotto temporaneo

## 📊 Metriche Successo

- [ ] Utenti possono selezionare metodo pagamento
- [x] Riepilogo preventivo visibile e accurato
- [x] Selezione flessibile partecipanti funzionante
- [ ] Link pagamento generati correttamente
- [ ] Email inviate con successo
- [ ] Pagamenti tracciati nel database

## 🔄 Prossimi Step Immediati

1. **Priorità Alta**:
   - Completare classe `BTR_Group_Payments`
   - Testare generazione link
   - Implementare endpoint pagamento

2. **Priorità Media**:
   - Template email HTML
   - Dashboard admin per monitoraggio
   - Sistema notifiche

3. **Priorità Bassa**:
   - Integrazione QR code reale
   - Report avanzati
   - Webhook per aggiornamenti stato

---
*Ultimo aggiornamento: 21 Gennaio 2025*