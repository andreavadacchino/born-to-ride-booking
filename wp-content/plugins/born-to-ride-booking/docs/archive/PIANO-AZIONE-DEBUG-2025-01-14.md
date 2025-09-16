# Piano d'Azione Debug e Ottimizzazione - Born to Ride Booking
**Data**: 2025-01-14  
**Versione Plugin**: 1.0.52

## 🎯 Obiettivo Principale
Risolvere le problematiche di calcolo prezzi, visualizzazione extra e gestione etichette dinamiche nel sistema di prenotazione.

## 📊 Priorità Task (basata su impatto business)

### 🔴 PRIORITÀ ALTA (Impatto economico diretto)

#### 1. **Fix Calcolo Supplementi Notti Extra** 
**Problema**: Doppio conteggio supplementi bambini, differenza 20€ per bambino  
**Files coinvolti**: 
- `class-btr-preventivi.php` (linee 89-94: prezzi hardcoded)
- `frontend-scripts.js` (calcolo supplementi)
- `class-btr-child-extra-night-pricing.php`

**Azioni**:
```php
// SOSTITUIRE prezzi hardcoded con sistema dinamico
// DA: $child_f1_price_per_night = 22.00;
// A: $child_f1_price_per_night = $this->get_dynamic_child_price('f1', 'extra_night');
```

#### 2. **Fix Visualizzazione Extra nel Riepilogo**
**Problema**: Extra con riduzioni (-60€) azzerano altri extra  
**Files coinvolti**:
- `templates/preventivo-review.php`
- `templates/preventivo-summary.php`
- `includes/functions.php` (btr_aggregate_extra_costs)

**Azioni**:
- Implementare logica separata per extra positivi e riduzioni
- Mostrare sempre tutti gli extra selezionati, indipendentemente dal totale

#### 3. **Extra nel Carrello WooCommerce**
**Problema**: Extra non visibili/modificabili nel carrello  
**Files coinvolti**:
- `class-btr-checkout.php`
- `class-btr-preventivi-ordini.php`

**Azioni**:
- Aggiungere extra come line items separati nel carrello
- Implementare UI per modifica quantità/rimozione

### 🟡 PRIORITÀ MEDIA (Usabilità e consistenza)

#### 4. **Etichette Bambini Dinamiche**
**Problema**: Etichette non consistenti tra frontend e backend  
**Files coinvolti**:
- `class-btr-dynamic-child-categories.php`
- `frontend-scripts.js`
- Templates vari

**Azioni**:
- Salvare snapshot etichette nel preventivo
- Propagare etichette salvate in tutto il flusso

#### 5. **Riordino Backend Extra**
**Problema**: Ordine extra cambia, necessità drag&drop  
**Files coinvolti**:
- `admin/views/extra-costs-admin.php`
- `class-btr-pacchetti-cpt.php`

**Azioni**:
- Aggiungere campo `order` ai metabox extra
- Implementare jQuery UI sortable

### 🟢 PRIORITÀ BASSA (Miglioramenti UX)

#### 6. **Condizionale Indirizzo Partecipanti**
**Problema**: Indirizzo richiesto sempre, necessario solo con assicurazioni  
**Files coinvolti**:
- `templates/preventivo-anagrafici.php`
- `frontend-scripts.js`

## 🔧 Implementazione Step-by-Step

### Step 1: Setup Ambiente Test (1 giorno)
```bash
# Creare branch dedicato
git checkout -b fix/calcoli-extra-notti-2025-01

# Backup database
wp db export backup-pre-fix-$(date +%Y%m%d).sql

# Attivare debug logging
define('BTR_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Step 2: Fix Calcolo Notti Extra (2-3 giorni)

1. **Creare classe centralizzata per calcoli**:
```php
// includes/class-btr-price-calculator.php
class BTR_Price_Calculator {
    private static $instance = null;
    private $cache = [];
    
    public function calculate_extra_nights($params) {
        // Logica centralizzata con cache
    }
    
    public function get_child_extra_night_price($fascia, $room_type = null) {
        // Prezzi dinamici da configurazione
    }
}
```

2. **Sostituire calcoli sparsi nel codice**
3. **Aggiungere unit tests**

### Step 3: Fix Visualizzazione Extra (2 giorni)

1. **Refactor funzione aggregazione**:
```php
function btr_aggregate_extra_costs($anagrafici, $options = []) {
    $result = [
        'additions' => [],    // Extra positivi
        'reductions' => [],   // Riduzioni
        'total' => 0
    ];
    // Logica separata per tipo
}
```

2. **Update templates per mostrare sezioni separate**

### Step 4: Extra nel Carrello (3 giorni)

1. **Modificare conversione preventivo->carrello**
2. **Aggiungere hook WooCommerce per gestione line items**
3. **Implementare UI modifica nel carrello**

## 📋 Testing Checklist

Per ogni fix implementato:

- [ ] Unit test specifico per la funzione
- [ ] Test manuale con diverse combinazioni:
  - [ ] Solo adulti
  - [ ] Mix adulti/bambini diverse fasce
  - [ ] Con/senza notti extra
  - [ ] Con/senza extra costs
  - [ ] Con riduzioni negative
- [ ] Verifica calcoli in:
  - [ ] Preventivo
  - [ ] Riepilogo
  - [ ] Carrello
  - [ ] Checkout
  - [ ] Ordine finale
- [ ] Test performance (no regression)
- [ ] Compatibilità browser (Chrome, Firefox, Safari)

## 🚀 Deployment Strategy

1. **Test su staging** (1 settimana minimo)
2. **Rilascio incrementale**:
   - v1.0.53: Fix calcolo notti extra
   - v1.0.54: Fix visualizzazione extra
   - v1.0.55: Extra nel carrello
   - v1.0.56: Etichette dinamiche
3. **Monitoraggio post-rilascio**:
   - Error logging attivo
   - Metriche conversione
   - Feedback utenti

## 📚 Documentazione da Aggiornare

- [ ] README con nuove features
- [ ] CHANGELOG dettagliato per versione
- [ ] Documentazione API interna
- [ ] Guide admin per nuove configurazioni
- [ ] Video tutorial per gestione extra

## ⚠️ Rischi e Mitigazioni

| Rischio | Probabilità | Impatto | Mitigazione |
|---------|-------------|---------|-------------|
| Breaking change calcoli | Media | Alto | Test regression completi |
| Performance degradation | Bassa | Medio | Profiling e cache |
| Dati inconsistenti DB | Media | Alto | Migration scripts + backup |
| Conflitti plugin terzi | Bassa | Basso | Test con stack completo |

## 📞 Comunicazione Team

- **Daily standup**: 9:30 per aggiornamenti
- **Code review**: Obbligatoria per ogni PR
- **Testing UAT**: Coinvolgere cliente per validazione
- **Documentazione**: Aggiornare man mano

## 🎯 Success Metrics

- Zero errori calcolo su 1000+ preventivi test
- Tempo caricamento < 2s per preventivi complessi
- 100% copertura test per funzioni critiche
- Riduzione ticket support del 80% su temi prezzi/extra

---

**Note**: Questo piano è modulare. Ogni sezione può essere implementata indipendentemente, permettendo rilasci incrementali e rollback mirati in caso di problemi.