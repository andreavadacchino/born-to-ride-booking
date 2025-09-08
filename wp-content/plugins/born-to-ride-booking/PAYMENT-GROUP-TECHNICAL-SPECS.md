# Specifiche Tecniche - Sistema Pagamento di Gruppo

## 🏗️ Architettura Implementata

### 1. **Flusso Utente Completo**

```
1. Compilazione Anagrafici (/inserisci-anagrafici/)
   ↓
2. Submit Form → AJAX save_anagrafici()
   ↓
3. Redirect a Pagina Selezione (/selezione-pagamento/)
   ↓
4. Utente sceglie "Pagamento di Gruppo"
   ↓
5. Selezione partecipanti e quote
   ↓
6. Submit → Generazione link pagamento
   ↓
7. Pagina riepilogo con link/QR
   ↓
8. Invio email ai partecipanti
   ↓
9. Partecipanti accedono tramite link
   ↓
10. Checkout individuale
```

### 2. **Struttura Dati**

#### Session Data (WooCommerce):
```php
$_SESSION['btr_group_participants'] = [
    [
        'index' => 0,
        'name' => 'Mario Rossi',
        'email' => 'mario@example.com',
        'shares' => 2  // Paga per 2 quote
    ],
    [
        'index' => 1,
        'name' => 'Luigi Verdi',
        'email' => 'luigi@example.com',
        'shares' => 1  // Paga per 1 quota
    ]
];
```

#### Database Schema:

**Tabella: wp_btr_group_payments**
```sql
payment_id          BIGINT UNSIGNED AUTO_INCREMENT
preventivo_id       BIGINT UNSIGNED
participant_index   INT
participant_name    VARCHAR(255)
participant_email   VARCHAR(255)
amount             DECIMAL(10,2)
payment_status     ENUM('pending', 'paid', 'failed', 'expired')
payment_type       ENUM('full', 'deposit', 'balance')
wc_order_id        BIGINT UNSIGNED NULL
payment_hash       CHAR(64)
created_at         DATETIME
paid_at            DATETIME NULL
expires_at         DATETIME
notes              TEXT NULL
```

**Tabella: wp_btr_payment_links**
```sql
link_id            BIGINT UNSIGNED AUTO_INCREMENT
payment_id         BIGINT UNSIGNED
link_hash          CHAR(64)
link_type          ENUM('individual', 'group', 'deposit', 'balance')
access_count       INT DEFAULT 0
last_access_at     DATETIME NULL
is_active          TINYINT(1) DEFAULT 1
created_at         DATETIME
expires_at         DATETIME
```

### 3. **Componenti Frontend**

#### JavaScript Functions:
```javascript
// Gestione selezione partecipanti
updateGroupTotals()     // Aggiorna totali quote e importi
validateGroupSelection() // Valida selezione prima submit

// Event Handlers
$('.participant-checkbox').on('change', ...)
$('.participant-shares').on('input', ...)
$('#btr-payment-plan-selection').on('submit', ...)
```

#### CSS Classes:
```css
.group-payment-config     /* Container configurazione gruppo */
.participants-table       /* Tabella partecipanti */
.participant-row         /* Riga singolo partecipante */
.participant-checkbox    /* Checkbox selezione */
.participant-shares      /* Input numero quote */
.participant-amount      /* Display importo */
.warning-message         /* Avvisi quote mancanti */
```

### 4. **AJAX Endpoints**

#### Creazione Piano Pagamento:
```
Action: btr_create_payment_plan
Nonce: btr_payment_plan_nonce
Data: {
    preventivo_id: 123,
    payment_plan: 'group_split',
    group_participants: [...]
}
Response: {
    success: true,
    data: {
        redirect_url: '/checkout/',
        message: 'Piano creato'
    }
}
```

#### Invio Link (da implementare):
```
Action: btr_send_payment_links
Nonce: btr_payment_links_nonce
Data: {
    preventivo_id: 123
}
Response: {
    success: true,
    data: {
        emails_sent: 5,
        message: '5 email inviate'
    }
}
```

## 🔌 Integrazioni

### 1. **WooCommerce**
- Utilizzo sessioni per mantenere dati
- Creazione prodotti custom per quote
- Hook checkout per associare pagamenti

### 2. **WordPress Core**
- Custom post type `btr_preventivi`
- Post meta per storing dati
- Rewrite rules per endpoint pagamento
- Nonce verification per sicurezza

### 3. **Database**
- Tabelle custom con prefix
- Foreign keys per integrità
- Indici per performance

## 🛠️ Utilities e Helper Functions

### PHP:
```php
btr_format_price_i18n($price)    // Formatta prezzi
btr_debug_log($message)           // Debug logging
sanitize_text_field()             // Sanitizzazione input
wp_verify_nonce()                 // Verifica sicurezza
```

### JavaScript:
```javascript
formatPrice(amount)               // Formatta prezzi lato client
Intl.NumberFormat('it-IT', ...)   // Localizzazione numeri
```

## 📁 File Structure

```
/wp-content/plugins/born-to-ride-booking/
├── templates/
│   └── payment-selection-page.php         [CREATO]
├── includes/
│   ├── class-btr-payment-selection-shortcode.php  [CREATO]
│   ├── class-btr-group-payments.php              [DA COMPLETARE]
│   ├── class-btr-shortcode-anagrafici.php        [MODIFICATO]
│   └── class-btr-database.php                    [ESISTENTE]
├── admin/
│   └── payment-settings-page.php                 [CREATO]
└── assets/
    ├── css/
    │   └── payment-selection.css                 [DA CREARE]
    └── js/
        └── payment-selection.js                  [DA CREARE]
```

## 🔐 Sicurezza

### Implementato:
- Nonce verification su tutti i form
- Sanitizzazione input utente
- Escape output HTML
- Validazione preventivo_id

### Da Implementare:
- Hash sicuri per link pagamento
- Scadenza link temporizzata
- Rate limiting accessi
- Logging tentativi accesso

## 🧪 Testing Checklist

### ✅ Completato:
- [x] Redirect da anagrafici a selezione pagamento
- [x] Visualizzazione riepilogo preventivo
- [x] Selezione piano pagamento base
- [x] Configurazione caparra dinamica
- [x] Selezione partecipanti gruppo
- [x] Calcolo quote multiple
- [x] Validazione form gruppo

### ⏳ Da Testare:
- [ ] Generazione link pagamento
- [ ] Accesso tramite link
- [ ] Creazione prodotto custom
- [ ] Completamento checkout individuale
- [ ] Aggiornamento stato pagamento
- [ ] Invio email con link
- [ ] Gestione link scaduti
- [ ] Dashboard admin

## 🚀 Performance Considerations

### Ottimizzazioni Implementate:
- Caching calcoli quote in JavaScript
- Query ottimizzate per recupero dati
- Lazy loading per grandi liste partecipanti

### Da Ottimizzare:
- Batch processing per invio email
- Queue system per generazione link
- Caching aggressivo dati preventivo
- CDN per assets statici

## 📋 TODO Prioritizzato

### 🔴 Critico (blocca il rilascio):
1. Completare `class-btr-group-payments.php`
2. Implementare endpoint `/btr-payment/{hash}`
3. Gestione prodotto WooCommerce per quote
4. Test end-to-end pagamento gruppo

### 🟡 Importante (post-rilascio v1):
1. Template email HTML
2. Dashboard admin monitoraggio
3. QR code reali (non placeholder)
4. Sistema reminder automatici

### 🟢 Nice to have (futuro):
1. Split payment automatico
2. Integrazione wallet digitali
3. Report analytics avanzati
4. API REST per app mobile

---
*Documento tecnico di riferimento per lo sviluppo*
*Ultimo aggiornamento: 21 Gennaio 2025*