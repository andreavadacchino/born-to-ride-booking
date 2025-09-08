# 📋 PIANO DI REFACTORING CONSERVATIVO v2.0
## Sistema di Calcolo Born to Ride - Analisi Tecnica Completa

---

## 🎯 OBIETTIVO PRIMARIO
Refactoring conservativo del sistema di calcolo preservando:
- ✅ Flusso di prenotazione frontend completo
- ✅ Tutte le interazioni utente esistenti
- ✅ Logica di business attuale (corretta)
- ✅ Compatibilità con preventivi esistenti

---

## 📊 MATRICE REGOLE DI CALCOLO ATTUALI

### A. PERCENTUALI BAMBINI (CRITICHE)

| Fascia | Extra Notti | Prezzo Base | Autorità |
|--------|-------------|-------------|----------|
| F1 (3-11) | 37.5% | 70% | Backend |
| F2 (12-14) | 50% | 75% | Backend |
| F3 | 70% | 80% | Backend |
| F4 | 80% | 85% | Backend |

**⚠️ CONFLITTI IDENTIFICATI:**
- Frontend ha 4 implementazioni diverse delle percentuali F3/F4
- Display fallback usa 50% per F3/F4 (SBAGLIATO)
- Backend è l'autorità (class-btr-preventivi.php:758-761)

### B. FORMULE DI CALCOLO CORE

#### FORMULA_001: Prezzo Totale Camera
```javascript
totale_camera = 
    (adulti * prezzo_per_persona) +
    (bambini_f1 * (prezzo_f1 || prezzo_per_persona * 0.7)) +
    (bambini_f2 * (prezzo_f2 || prezzo_per_persona * 0.75)) +
    (bambini_f3 * (prezzo_f3 || prezzo_per_persona * 0.8)) +
    (bambini_f4 * (prezzo_f4 || prezzo_per_persona * 0.85)) +
    (supplemento_per_persona * persone_totali * notti)
```

#### FORMULA_002: Extra Notti
```javascript
extra_notti_totale = 
    (adulti * extra_night_pp * notti_extra) +
    (bambini_f1 * extra_night_pp * 0.375 * notti_extra) +
    (bambini_f2 * extra_night_pp * 0.5 * notti_extra) +
    (bambini_f3 * extra_night_pp * 0.7 * notti_extra) +
    (bambini_f4 * extra_night_pp * 0.8 * notti_extra) +
    (supplemento_pp * persone_totali * notti_extra)
```

#### FORMULA_003: Totale Generale
```javascript
totale_generale = 
    totale_camere +
    totale_extra_notti +
    totale_assicurazioni +
    totale_costi_extra
```

### C. REGOLE DI VALIDAZIONE

| ID | Regola | Implementazione |
|----|--------|-----------------|
| V01 | Bambini non in singole | frontend-scripts.js:2263 |
| V02 | Min 1 adulto per camera con bambini | frontend-scripts.js:2284 |
| V03 | Capacità massima camera | frontend-scripts.js:2176 |
| V04 | Neonati (0-2) gratuiti | Ovunque |

---

## 🏗️ STRATEGIA DI REFACTORING - APPROCCIO INCREMENTALE

### FASE 0: PREPARAZIONE (1 settimana)
```
TASK_001: Setup ambiente di test isolato
TASK_002: Backup completo database e codice
TASK_003: Creazione suite di test per preventivi esistenti
TASK_004: Documentazione snapshot calcoli attuali
TASK_005: Setup feature flags per rollout progressivo
```

### FASE 1: CENTRALIZZAZIONE CALCOLI (2 settimane)

#### TASK_010: Creare Calculator Service Unificato
```php
// includes/services/class-btr-calculator-service.php
class BTR_Calculator_Service {
    
    // Configurazione centralizzata (NO HARDCODING)
    private $config = [
        'child_percentages' => [
            'f1' => ['extra_night' => 0.375, 'base' => 0.70],
            'f2' => ['extra_night' => 0.50,  'base' => 0.75],
            'f3' => ['extra_night' => 0.70,  'base' => 0.80],
            'f4' => ['extra_night' => 0.80,  'base' => 0.85]
        ]
    ];
    
    public function calculate_room_price($room_data) {
        // SINGOLA implementazione della FORMULA_001
    }
    
    public function calculate_extra_nights($participants, $nights) {
        // SINGOLA implementazione della FORMULA_002
    }
}
```

#### TASK_011: API Endpoint Unico
```php
// includes/api/class-btr-calculator-api.php
register_rest_route('btr/v2', '/calculate', [
    'methods' => 'POST',
    'callback' => [$this, 'handle_calculation'],
    'permission_callback' => [$this, 'verify_nonce'],
    'args' => $this->get_validation_schema()
]);
```

#### TASK_012: Validation Layer
```php
// includes/validators/class-btr-booking-validator.php
class BTR_Booking_Validator {
    public function validate_room_assignment($room, $participants) {
        // Implementa V01, V02, V03, V04
    }
}
```

### FASE 2: ADAPTER PATTERN PER FRONTEND (2 settimane)

#### TASK_020: Frontend Adapter Layer
```javascript
// assets/js/btr-calculator-adapter.js
class BTRCalculatorAdapter {
    constructor() {
        this.legacyMode = window.BTR_USE_LEGACY || false;
    }
    
    async calculate() {
        if (this.legacyMode) {
            // Usa vecchio sistema per compatibilità
            return this.legacyCalculate();
        }
        
        // Chiama nuovo API endpoint
        return await this.apiCalculate();
    }
    
    // Mantiene STESSA interfaccia per frontend esistente
    updateUI(result) {
        $('#btr-total-price').text(result.total);
        // Compatibile con DOM esistente
    }
}
```

#### TASK_021: Progressive Enhancement
```javascript
// Sostituisci gradualmente chiamate dirette
// DA:
totalPrice = calculateRoomPrice(roomData);

// A:
totalPrice = await calculator.getRoomPrice(roomData);
```

#### TASK_022: Event System Preservation
```javascript
// Mantieni sistema eventi esistente
$(document).on('change', '.btr-room-quantity', async function() {
    // Usa nuovo calculator ma mantieni eventi
    const result = await calculator.recalculate();
    $(document).trigger('btr:price:updated', result);
});
```

### FASE 3: CLEANUP DEBITO TECNICO (1 settimana)

#### TASK_030: Rimuovi Calcoli Duplicati
```javascript
// Identifica e rimuovi gradualmente:
// - 15 implementazioni frontend diverse
// - 22 implementazioni backend diverse  
// - 10 implementazioni unified calculator
```

#### TASK_031: Fix Percentuali F3/F4
```javascript
// Correggi TUTTI i fallback sbagliati:
// WRONG: f3 = 0.5, f4 = 0.5
// RIGHT: f3 = 0.7, f4 = 0.8
```

#### TASK_032: Consolidamento Data Flow
```php
// Unifica payload structure
class BTR_Booking_Payload {
    private $schema = [
        'participants' => [...],
        'rooms' => [...],
        'extras' => [...]
    ];
    
    public function validate($data) {
        // Schema validation unico
    }
}
```

### FASE 4: TESTING E VALIDAZIONE (1 settimana)

#### TASK_040: Test Regressione
```php
class BTR_Regression_Tests {
    public function test_all_existing_quotes() {
        $quotes = $this->get_all_quotes();
        foreach($quotes as $quote) {
            $old_total = $quote->get_meta('_totale_generale');
            $new_total = $calculator->calculate($quote->data);
            $this->assertWithinTolerance($old_total, $new_total, 0.01);
        }
    }
}
```

#### TASK_041: Test Unitari
```php
// 50+ test per ogni combinazione:
- test_2_adults_1_child_extra_night()
- test_group_payment_split()
- test_insurance_calculations()
- test_f3_f4_percentages()
```

#### TASK_042: Test E2E
```javascript
// Playwright tests per flusso completo
await page.goto('/pacchetto-test');
await page.fill('#btr_num_adults', '2');
await page.fill('#btr_num_child_f1', '1');
await page.click('#btr_extra_night');
await expect(page.locator('#total')).toHaveText('€524.30');
```

### FASE 5: ROLLOUT PROGRESSIVO (1 settimana)

#### TASK_050: Feature Flags
```php
// wp-config.php
define('BTR_USE_NEW_CALCULATOR', true);
define('BTR_CALCULATOR_VERSION', '2.0');
```

#### TASK_051: A/B Testing
```php
if (get_user_meta($user_id, 'btr_beta_tester', true)) {
    // Usa nuovo sistema
} else {
    // Usa vecchio sistema
}
```

#### TASK_052: Monitoring
```php
// Log discrepanze per analisi
if (abs($old_total - $new_total) > 0.01) {
    error_log("DISCREPANCY: Quote $id - Old: $old_total, New: $new_total");
}
```

---

## 🔧 DETTAGLI TECNICI IMPLEMENTAZIONE

### 1. PRESERVAZIONE FRONTEND

**ELEMENTI DA MANTENERE:**
```html
<!-- Form IDs - NON MODIFICARE -->
#btr_num_adults
#btr_num_child_f1, f2, f3, f4
#btr_num_infants
#btr_extra_night
.btr-room-quantity

<!-- Event Handlers - PRESERVARE -->
$(document).on('change input', '.btr-room-quantity', ...)
$(document).on('btr:state:updated', ...)
$('#btr-check-people').on('click', ...)
```

### 2. MIGRATION STRATEGY

```javascript
// Adapter pattern per compatibilità
class BTRCompatibilityLayer {
    // Vecchia firma
    calculateRoomPrice(adults, children, nights) {
        // Chiama nuovo sistema internamente
        return this.newCalculator.calculate({
            participants: {adults, children},
            nights: nights
        });
    }
}
```

### 3. DATABASE COMPATIBILITY

```sql
-- Mantieni struttura meta esistente
-- Aggiungi nuovi meta per tracking
INSERT INTO postmeta (post_id, meta_key, meta_value)
VALUES 
    (quote_id, '_calculation_version', '2.0'),
    (quote_id, '_calculation_timestamp', NOW()),
    (quote_id, '_calculation_hash', SHA256(data));
```

---

## 📈 METRICHE DI SUCCESSO

| Metrica | Target | Misurazione |
|---------|--------|-------------|
| Discrepanza calcoli | < 0.01€ | Regression test |
| Performance | < 500ms | API response time |
| Compatibilità | 100% | Preventivi esistenti |
| Test coverage | > 80% | Jest/PHPUnit |
| Error rate | < 0.1% | Monitoring |

---

## 🚨 RISCHI E MITIGAZIONI

| Rischio | Probabilità | Impatto | Mitigazione |
|---------|------------|---------|-------------|
| Breaking changes frontend | Media | Alto | Adapter pattern + testing |
| Discrepanze calcoli | Bassa | Alto | Regression test suite |
| Performance degradation | Bassa | Medio | Caching + optimization |
| Data migration issues | Media | Alto | Backup + rollback plan |

---

## 🎯 DELIVERABLES FINALI

1. **Calculator Service**: Classe PHP unificata con TUTTE le formule
2. **REST API**: Endpoint `/wp-json/btr/v2/calculate` validato
3. **Frontend Adapter**: Layer di compatibilità JavaScript
4. **Test Suite**: 50+ test unitari + E2E
5. **Documentation**: Mapping completo regole di calcolo
6. **Migration Guide**: Istruzioni per rollout progressivo

---

## ⏱️ TIMELINE REALISTICA

| Fase | Durata | Dipendenze |
|------|--------|------------|
| Preparazione | 1 settimana | - |
| Centralizzazione | 2 settimane | Preparazione |
| Adapter Pattern | 2 settimane | Centralizzazione |
| Cleanup | 1 settimana | Adapter |
| Testing | 1 settimana | Cleanup |
| Rollout | 1 settimana | Testing |
| **TOTALE** | **8 settimane** | - |

---

## 🔑 CRITICAL SUCCESS FACTORS

1. **NON riscrivere il frontend** - Usa adapter pattern
2. **Test OGNI preventivo esistente** - Zero regressioni
3. **Rollout progressivo** - Feature flags obbligatori
4. **Monitoring continuo** - Log tutte le discrepanze
5. **Documentazione completa** - Ogni formula documentata

---

**Versione**: 2.0  
**Data**: 30/08/2025  
**Stato**: PIANO TECNICO COMPLETO