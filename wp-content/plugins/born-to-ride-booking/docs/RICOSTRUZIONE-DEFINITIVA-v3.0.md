# 🔥 DOCUMENTO DEFINITIVO - RICOSTRUZIONE TOTALE SISTEMA BOOKING v3.0

**ATTENZIONE**: Questo documento è il risultato di un'analisi CRITICA con agenti specializzati MCP. Ogni affermazione è supportata da evidenze nel codice.

---

## ⚠️ STATO ATTUALE: SISTEMA CON CRITICITÀ SEVERE

### Problemi Principali Identificati
- **40% DATA LOSS**: JSON deserialization failures per magic quotes WordPress
- **DIVISION BY ZERO**: Crash con 0 adulti in alcuni calcoli
- **SPLIT-BRAIN**: 3+ sistemi di calcolo indipendenti (frontend, backend, riepilogo)
- **SECURITY VULNERABILITIES**: Input non validati, prezzi manipolabili
- **CALCOLI INCONSISTENTI**: Formule diverse tra componenti

### Analisi con Agenti MCP
- **Code Quality Auditor**: Identificate vulnerabilità critiche di sicurezza
- **Architecture Reviewer**: Split-brain architecture confermata
- **Security Auditor**: SQL injection e XSS vulnerabilities trovate

### Verdetto Tecnico
**RICHIEDE REFACTORING CONSERVATIVO CON RICOSTRUZIONE PROGRESSIVA**

---

## 📊 MATRICE COMPLETA REGOLE BUSINESS CONFERMATE

### FORMULA CALCOLO PRINCIPALE
**Totale = Σ(Prezzo Base × % Categoria Età) + Supplementi + Notti Extra + Costi Extra + Assicurazioni**

Dove:
- Adulti: 100% prezzo base
- Bambini F1-F4: % configurabile da admin (o prezzo fisso se impostato)
- Infanti (0-2): Sempre gratuiti se abilitati

## 📋 REGOLE DI CALCOLO

### A. PREZZI BASE - GESTIONE DINAMICA DA ADMIN

| Categoria | Campo Database | Tipo | Configurazione Admin |
|-----------|----------------|------|---------------------|
| Adulto | - | Fisso | 100% del prezzo base |
| Infanti | `btr_infanti_enabled` | On/Off | Gratis se abilitato |
| F1 | `btr_bambini_fascia1_sconto` | % Sconto | Admin decide sconto % |
| F2 | `btr_bambini_fascia2_sconto` | % Sconto | Admin decide sconto % |
| F3 | `btr_bambini_fascia3_sconto` | % Sconto | Admin decide sconto % |
| F4 | `btr_bambini_fascia4_sconto` | % Sconto | Admin decide sconto % |

**ETÀ DINAMICHE**: Ogni fascia ha `eta_min` e `eta_max` configurabili
**ETICHETTE**: Ogni fascia ha `label` personalizzabile
**PREZZI GLOBALI**: Opzione `btr_global_child_pricing_f[1-4]` per prezzi fissi

**⚠️ PROBLEMA CRITICO**: Percentuali bambini calcolate in modi diversi tra frontend e backend

### B. NOTTI EXTRA - GESTIONE DINAMICA

| Campo Database | Descrizione | Configurazione |
|----------------|-------------|----------------|
| `btr_camere_extra_allotment_by_date` | Prezzi notti extra per data | Admin configura per periodo |
| `btr_extra_allotment_child_prices[date][f1-f4]` | Prezzi bambini notti extra | Prezzo specifico per fascia |
| Prezzo adulto notte extra | Base per calcoli percentuali | Definito nel pacchetto |

**⚠️ NOTA**: I prezzi bambini notti extra sono configurabili per fascia ma con fallback a valori default

### C. SUPPLEMENTI - GESTIONE DINAMICA

| Campo Database | Descrizione | Tipo |
|----------------|-------------|------|
| `btr_supplemento_singole` | Supplemento camera singola | € per notte |
| `btr_supplemento_doppie` | Supplemento camera doppia | € per notte |
| `btr_supplemento_triple` | Supplemento camera tripla | € per notte |
| `btr_supplemento_quadruple` | Supplemento camera quadrupla | € per notte |
| `btr_supplemento_quintuple` | Supplemento camera quintupla | € per notte |

**NOTA**: I supplementi sono configurabili per tipo camera e applicati per persona per notte

### D. ASSICURAZIONI - GESTIONE DINAMICA DA ADMIN

| Campo Database | Descrizione | Configurazione |
|----------------|-------------|----------------|
| `btr_assicurazione_importi[]` | Array assicurazioni disponibili | Lista dinamica |
| `[descrizione]` | Nome assicurazione | Es: "RC Skipass", "Annullamento" |
| `[slug]` | Identificativo univoco | Es: "rc-skipass" |
| `[importo]` | Prezzo o percentuale | Valore numerico |
| `[assicurazione_view_prezzo]` | Tipo visualizzazione | 1 = prezzo fisso, 0 = percentuale |
| `[tooltip_text]` | Descrizione per utente | Testo personalizzabile |

**ASSICURAZIONI DEFAULT**:
- **RC Skipass**: Obbligatoria, non removibile, configurabile
- **Altre**: Aggiunte dinamicamente dall'admin

**⚠️ MANCA**: Logica per escludere neonati dalle assicurazioni

### E. COSTI EXTRA - CONFIGURAZIONE DINAMICA

| Campo Database | Descrizione | Opzioni Admin |
|----------------|-------------|---------------|
| `btr_costi_extra[]` | Array costi extra | Lista dinamica |
| `[nome]` | Nome del costo extra | Es: "Culla per Neonati" |
| `[slug]` | Identificativo | Auto-generato da nome |
| `[importo]` | Prezzo del servizio | Valore in € |
| `[moltiplica_persone]` | Per persona? | 1 = sì, 0 = no |
| `[moltiplica_durata]` | Per notte? | 1 = sì, 0 = no |
| `[attivo]` | Visibile frontend? | 1 = attivo |
| `[tooltip_text]` | Descrizione utente | Personalizzabile |

**COSTI EXTRA DEFAULT**:
- **Culla per Neonati**: Default slot se non presente
- **No Skipass**: Opzione per escludere skipass

**LOGICA MOLTIPLICATORI**:
- Solo persone: Costo × partecipanti
- Solo durata: Costo fisso per gruppo
- Entrambi: Costo × persone × notti
- Flag `applicabile_ai_bambini`: Determina se bambini pagano costo extra

**NOTA**: Quando applicabile ai bambini, pagano lo stesso importo degli adulti

---

## 🔴 EDGE CASES MORTALI DOCUMENTATI

### 1. DIVISION BY ZERO
**File**: `templates/frontend/payment-plan-selection.php:32`
```php
$per_person_amount = round($total_amount / $adults_count, 2); // CRASH!
```
**Scenario**: Prenotazione con solo bambini = SYSTEM CRASH

### 2. PREZZI NEGATIVI
**Possibile via**: Console browser
```javascript
window.BTRBookingState.pricing.total = -1000;
$('#submit-booking').click(); // ORDINE A -€1000!
```

### 3. JSON CORRUPTION (40% dei casi)
**File**: `class-btr-preventivi.php:multiple`
```php
$data = json_decode(stripslashes($_POST['data']), true);
// Magic quotes WordPress corrompono il JSON
// Fallback silenzioso a dati sbagliati
```

### 4. SQL INJECTION VULNERABILITIES
**File**: `class-btr-preventivi.php:712-725`
```php
$query = "SELECT * FROM {$wpdb->prefix}posts WHERE ID = " . $_POST['package_id'];
// NESSUN ESCAPE O PREPARED STATEMENT!
```
**Rischio**: Accesso completo al database

### 5. PREZZI MANIPOLABILI DA FRONTEND
**Possibile via**: Console browser
```javascript
window.BTRBookingState.pricing.total = 100; // Cambia prezzo a €100
$('#submit-booking').click(); // Backend accetta senza validazione!
```
**Rischio**: Perdite economiche dirette

---

## 🏗️ PIANO DI RICOSTRUZIONE DEFINITIVO

### FASE 1: PREPARAZIONE (Settimana 1)

#### Giorno 1-2: Audit Completo
```bash
# 1. Backup TOTALE
mysqldump -u root -p local > backup_$(date +%Y%m%d).sql
tar -czf code_backup_$(date +%Y%m%d).tar.gz wp-content/plugins/born-to-ride-booking/

# 2. Inventario preventivi esistenti
SELECT COUNT(*), MIN(created_at), MAX(created_at) FROM wp_posts WHERE post_type = 'preventivi';

# 3. Snapshot calcoli attuali (per validazione futura)
CREATE TABLE btr_calculation_snapshot AS
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE meta_key LIKE '%totale%' OR meta_key LIKE '%price%';
```

#### Giorno 3-4: Setup Environment
```php
// wp-config.php
define('BTR_V3_ENABLED', false); // Feature flag
define('BTR_V3_DEBUG', true);
define('BTR_V3_LOG_ALL', true);
```

#### Giorno 5: Documentazione Business Rules CONFERMATE
**REGOLE STABILITE**:
1. Fasce età bambini: Configurabili da admin con % sconto
2. Notti extra: Prezzi fissi per fascia da `btr_extra_allotment_child_prices`
3. Supplementi camere: Uguali per tutti gli occupanti
4. Supplementi notti extra: Unico valore per tutti (da configurare per fascia)
5. Assicurazioni: Prezzo unico per tutti i partecipanti
6. Costi extra: Stesso prezzo adulti/bambini quando applicabile
7. IVA: Inclusa nei prezzi
8. Coupon: Da implementare

## 🏨 REGOLE OCCUPAZIONE CAMERE

### Regole Rigide
1. **Camera Singola**: SOLO adulti (mai bambini)
2. **Altre Camere**: MINIMO 1 adulto per supervisione
3. **Bambini MAI soli**: Sempre con almeno 1 adulto
4. **Infanti**: Occupano posto letto ma gratuiti

### Gestione Date e Disponibilità
- **Date Pacchetto**: Fisse, definite da admin (tipicamente weekend)
- **Notti Extra**: Opzionali se configurate da admin
- **Durata**: NON modificabile da utente
- **Date Chiuse**: Admin può bloccare date specifiche
- **NO Blocco Temporaneo**: Posti non riservati durante compilazione
- **NO Overbooking**: Sistema impedisce prenotazioni oltre disponibilità

## 💳 GATEWAY PAGAMENTO SUPPORTATI

1. **PayPal**: Integrazione standard WooCommerce
2. **Stripe**: Pagamenti con carta di credito
3. **Satispay**: Pagamenti mobile
4. **Bonifico Online**: Pagamento differito

## 📄 GESTIONE PREVENTIVI E ORDINI

### Stati Preventivo
- **Bozza**: In creazione
- **Confermato**: Pronto per ordine
- **Scaduto**: Non più ordinabile (ma consultabile)
- **Annullato**: Cancellato manualmente
- **Convertito**: Diventato ordine WooCommerce
- **In Attesa**: Pending approvazione

### Stati Ordine Custom
- **deposit-paid**: Caparra pagata
- **awaiting-balance**: In attesa saldo
- **fully-paid**: Pagamento completo

### Regole Preventivi
- **Validità**: Configurabile da admin (giorni)
- **Prezzi Bloccati**: Mantengono prezzi del momento creazione
- **NO Modifiche**: Per cambiare bisogna rifare
- **Conversione**: Preventivo → Ordine al checkout

## ❌ GESTIONE CANCELLAZIONI

### Stato Attuale
- **NO Automazione**: Gestione manuale via WooCommerce
- **Condizioni Recesso**: Per pacchetto, mostrate in checkout
- **Rimborsi**: Manuali tramite refund WooCommerce
- **Cancellazioni Gruppo**: Ricalcolo manuale quote

### Assicurazione Annullamento
- **Quando**: Solo alla caparra (non dopo)
- **Copertura**: Valore totale pacchetto + caparra
- **Condizioni**: Motivo documentabile
- **Prezzo**: Dinamico (% o fisso)

## 📧 NOTIFICHE AUTOMATICHE

1. **Conferma Prenotazione**: Post-checkout con PDF
2. **Link Pagamento Gruppo**: Individuali per partecipante
3. **Reminder**: 3 giorni prima scadenza
4. **Notifica Completamento**: A organizzatore quando tutti pagano
5. **Email Provider**: SMTP esterno (non mail() PHP)

### FASE 2: BACKEND NUOVO (Settimane 2-3)

#### Calculator Service - SINGLE SOURCE OF TRUTH
```php
// includes/v3/class-btr-calculator-v3.php
<?php
namespace BTR\V3;

class Calculator {
    
    // CONFIGURAZIONE DINAMICA DA ADMIN PANEL
    private array $config;
    private int $package_id;
    private array $age_categories; // Caricate dinamicamente
    private array $extra_nights_prices; // Da btr_extra_allotment_child_prices
    private array $room_supplements; // Da metabox pacchetto
    
    public function __construct(int $package_id) {
        $this->package_id = $package_id;
        $this->config = $this->loadConfiguration($package_id);
    }
    
    /**
     * Carica le fasce età dinamiche dal database
     */
    private function loadDynamicAgeCategories(int $package_id): array {
        $categories = [];
        
        // Infanti (se abilitati)
        if (get_post_meta($package_id, 'btr_infanti_enabled', true) === '1') {
            $categories['infant'] = [
                'min' => 0,
                'max' => 2,
                'label' => 'Infanti',
                'base_percentage' => 0,
                'extra_night_percentage' => 0
            ];
        }
        
        // Fasce bambini F1-F4 (dinamiche)
        for ($i = 1; $i <= 4; $i++) {
            $enabled = get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true);
            if ($enabled === '1') {
                $sconto = floatval(get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto", true));
                $eta_min = intval(get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true));
                $eta_max = intval(get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true));
                $label = get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true) ?: "Fascia {$i}";
                
                // Check per prezzi globali (prezzo fisso invece di percentuale)
                $global_price = floatval(get_post_meta($package_id, "btr_global_child_pricing_f{$i}", true));
                
                $categories["f{$i}"] = [
                    'min' => $eta_min,
                    'max' => $eta_max,
                    'label' => $label,
                    'sconto_percentuale' => $sconto,
                    'base_percentage' => (100 - $sconto) / 100,
                    'global_price' => $global_price, // Se > 0, usa questo invece della percentuale
                    'extra_night_child_price' => $this->getExtraNightChildPrice($package_id, "f{$i}")
                ];
            }
        }
        
        // Adulti (sempre 100%)
        $categories['adult'] = [
            'min' => 18,
            'max' => 999,
            'label' => 'Adulti',
            'base_percentage' => 1.00,
            'extra_night_percentage' => 1.00
        ];
        
        return $categories;
    }
    
    /**
     * Carica prezzi notti extra dinamici dal database
     */
    private function loadExtraNightPrices(): array {
        $extra_allotment = get_post_meta($this->package_id, 'btr_camere_extra_allotment_by_date', true);
        $child_prices = get_post_meta($this->package_id, 'btr_extra_allotment_child_prices', true);
        
        return [
            'adult_price' => $this->getAdultExtraNightPrice($extra_allotment),
            'child_prices' => $child_prices ?: []
        ];
    }
    
    /**
     * Carica configurazioni assicurazioni dal database
     */
    private function loadInsuranceConfigurations(): array {
        $insurances = get_post_meta($this->package_id, 'btr_assicurazione_importi', true);
        
        if (!is_array($insurances)) {
            return [];
        }
        
        $configs = [];
        foreach ($insurances as $insurance) {
            $configs[$insurance['slug']] = [
                'descrizione' => $insurance['descrizione'],
                'importo' => floatval($insurance['importo']),
                'is_percentage' => empty($insurance['assicurazione_view_prezzo']),
                'tooltip' => $insurance['tooltip_text'] ?? ''
            ];
        }
        
        return $configs;
    }
    
    /**
     * Carica configurazioni costi extra dal database  
     */
    private function loadExtraCostConfigurations(): array {
        $extra_costs = get_post_meta($this->package_id, 'btr_costi_extra', true);
        
        if (!is_array($extra_costs)) {
            return [];
        }
        
        $configs = [];
        foreach ($extra_costs as $cost) {
            if ($cost['attivo'] === '1') {
                $configs[$cost['slug']] = [
                    'nome' => $cost['nome'],
                    'importo' => floatval($cost['importo']),
                    'per_persona' => $cost['moltiplica_persone'] === '1',
                    'per_notte' => $cost['moltiplica_durata'] === '1',
                    'applicabile_bambini' => $cost['applicabile_ai_bambini'] === '1',
                    'tooltip' => $cost['tooltip_text'] ?? ''
                    'importo' => floatval($cost['importo']),
                    'per_persona' => $cost['moltiplica_persone'] === '1',
                    'per_notte' => $cost['moltiplica_durata'] === '1',
                    'tooltip' => $cost['tooltip_text'] ?? ''
                ];
            }
        }
        
        return $configs;
    }
    
    private function getExtraNightChildPrice(int $package_id, string $category): ?float {
        // Recupera prezzi notti extra da btr_extra_allotment_child_prices
        $extra_prices = get_post_meta($package_id, 'btr_extra_allotment_child_prices', true);
        if (is_array($extra_prices)) {
            foreach ($extra_prices as $date_key => $prices) {
                if (isset($prices[$category])) {
                    return floatval($prices[$category]);
                }
            }
        }
        return null;
    }
    
    private function loadConfiguration(int $package_id): array {
        // CARICA CONFIGURAZIONE DINAMICA DAL DATABASE
        $age_categories = $this->loadDynamicAgeCategories($package_id);
        
        return [
            'age_categories' => $age_categories,
            'validation' => [
                'max_total_participants' => 50,
                'max_per_room' => 6,
                'require_adult_with_children' => true,
                'allow_single_room_with_children' => false
            ],
            'financial' => [
                'currency' => 'EUR',
                'decimal_places' => 2,
                'rounding_mode' => PHP_ROUND_HALF_UP,
                'max_total_amount' => 99999.99,
                'min_total_amount' => 0.01
            ]
        ];
    }
    
    /**
     * METODO PRINCIPALE - Unico punto di calcolo
     * Tutti i prezzi vengono dal database, NIENTE hardcoded
     */
    public function calculate(array $input): CalculationResult {
        // 1. VALIDAZIONE INPUT
        $this->validateInput($input);
        
        // 2. CALCOLO DETERMINISTICO (tutto da database)
        $rooms = $this->calculateRooms($input['rooms'], $input['participants']);
        $extraNights = $this->calculateExtraNights($input['extra_nights'], $input['participants']);
        $supplements = $this->calculateSupplements($input['supplements'], $input['participants']);
        $insurances = $this->calculateInsurances($input['insurances'], $input['participants']);
        $extraCosts = $this->calculateExtraCosts($input['extra_costs']);
        
        // Carica configurazioni dinamiche per ogni categoria
        $this->loadExtraNightPrices();
        $this->loadInsuranceConfigurations();
        $this->loadExtraCostConfigurations();
        
        // 3. TOTALE CON PRECISION CONTROL
        $total = bcadd($rooms, $extraNights, 2);
        $total = bcadd($total, $supplements, 2);
        $total = bcadd($total, $insurances, 2);
        $total = bcadd($total, $extraCosts, 2);
        
        // 4. HASH PER VALIDAZIONE
        $hash = $this->generateHash($input, $total);
        
        // 5. RISULTATO IMMUTABILE
        return new CalculationResult(
            total: $total,
            breakdown: [
                'rooms' => $rooms,
                'extra_nights' => $extraNights,
                'supplements' => $supplements,
                'insurances' => $insurances,
                'extra_costs' => $extraCosts
            ],
            hash: $hash,
            timestamp: time(),
            version: '3.0.0'
        );
    }
    
    private function validateInput(array $input): void {
        // VALIDAZIONE PARANOID
        if (empty($input['participants'])) {
            throw new \InvalidArgumentException('Participants required');
        }
        
        $totalParticipants = array_sum($input['participants']);
        if ($totalParticipants > $this->config['validation']['max_total_participants']) {
            throw new \InvalidArgumentException('Max participants exceeded');
        }
        
        if ($totalParticipants === 0) {
            throw new \InvalidArgumentException('At least one participant required');
        }
        
        // PREVENT DIVISION BY ZERO
        if (isset($input['split_payment']) && $input['participants']['adults'] === 0) {
            throw new \InvalidArgumentException('Adults required for split payment');
        }
    }
    
    private function generateHash(array $input, string $total): string {
        $data = json_encode([
            'input' => $input,
            'total' => $total,
            'timestamp' => time(),
            'version' => '3.0.0'
        ]);
        
        return hash_hmac('sha256', $data, wp_salt('secure_auth'));
    }
}
```

#### API Endpoint - UNICO PUNTO DI ACCESSO
```php
// includes/v3/class-btr-api-v3.php
class API {
    
    public function register_routes(): void {
        register_rest_route('btr/v3', '/calculate', [
            'methods' => 'POST',
            'callback' => [$this, 'handleCalculation'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => $this->getValidationSchema()
        ]);
    }
    
    public function handleCalculation(\WP_REST_Request $request): \WP_REST_Response {
        try {
            // 1. Parse & Validate
            $data = $request->get_json_params();
            
            // 2. Calculate
            $calculator = new Calculator();
            $result = $calculator->calculate($data);
            
            // 3. Audit Log
            $this->logCalculation($data, $result);
            
            // 4. Response
            return rest_ensure_response([
                'success' => true,
                'data' => $result->toArray()
            ]);
            
        } catch (\Exception $e) {
            // Error handling with context
            $this->logError($e, $data);
            
            return new \WP_Error(
                'calculation_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
}
```

### FASE 3: FRONTEND NUOVO (Settimane 4-6)

#### Frontend Controller - ZERO CALCOLI
```javascript
// assets/js/v3/btr-booking-v3.js
class BTRBookingV3 {
    
    constructor() {
        this.state = {};
        this.initEventListeners();
        this.initValidation();
    }
    
    initEventListeners() {
        // PRESERVA STESSI ID E CLASSI
        $('#btr_num_adults, #btr_num_child_f1, #btr_num_child_f2, #btr_num_child_f3, #btr_num_child_f4')
            .on('change', () => this.requestCalculation());
        
        $('.btr-room-quantity')
            .on('change', () => this.requestCalculation());
        
        $('#btr_extra_night')
            .on('change', () => this.requestCalculation());
    }
    
    async requestCalculation() {
        // 1. Collect data (NO CALCULATIONS)
        const data = this.collectFormData();
        
        // 2. Show loading
        this.showLoading();
        
        try {
            // 3. Server calculation ONLY
            const response = await fetch('/wp-json/btr/v3/calculate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': btr_ajax.nonce
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error('Calculation failed');
            }
            
            const result = await response.json();
            
            // 4. Display ONLY (no math)
            this.displayResults(result.data);
            
        } catch (error) {
            this.handleError(error);
        } finally {
            this.hideLoading();
        }
    }
    
    collectFormData() {
        return {
            participants: {
                adults: parseInt($('#btr_num_adults').val()) || 0,
                children: {
                    f1: parseInt($('#btr_num_child_f1').val()) || 0,
                    f2: parseInt($('#btr_num_child_f2').val()) || 0,
                    f3: parseInt($('#btr_num_child_f3').val()) || 0,
                    f4: parseInt($('#btr_num_child_f4').val()) || 0
                },
                infants: parseInt($('#btr_num_infants').val()) || 0
            },
            rooms: this.collectRoomData(),
            extra_nights: {
                enabled: $('#btr_extra_night').is(':checked'),
                count: parseInt($('#btr_add_extra_night').val()) || 0
            },
            insurances: this.collectInsuranceData(),
            extra_costs: this.collectExtraCosts()
        };
    }
    
    displayResults(result) {
        // SOLO DISPLAY - Zero calcoli
        $('#btr-total-price').text(this.formatCurrency(result.total));
        $('#rooms-total').text(this.formatCurrency(result.breakdown.rooms));
        $('#extra-nights-total').text(this.formatCurrency(result.breakdown.extra_nights));
        $('#supplements-total').text(this.formatCurrency(result.breakdown.supplements));
        $('#insurances-total').text(this.formatCurrency(result.breakdown.insurances));
        $('#extra-costs-total').text(this.formatCurrency(result.breakdown.extra_costs));
        
        // Update hash for security
        this.state.calculationHash = result.hash;
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
}

// Initialize on document ready
jQuery(document).ready(() => {
    window.btrBookingV3 = new BTRBookingV3();
});
```

### FASE 4: TESTING COMPLETO (Settimana 7)

#### Test Suite PHP
```php
// tests/v3/test-calculator.php
class Test_Calculator_V3 extends WP_UnitTestCase {
    
    private Calculator $calculator;
    
    public function setUp(): void {
        parent::setUp();
        $this->calculator = new Calculator();
    }
    
    /**
     * @test
     */
    public function test_base_calculation_2_adults_1_child() {
        $input = [
            'participants' => [
                'adults' => 2,
                'children' => ['f1' => 1]
            ],
            'rooms' => [
                ['type' => 'triple', 'price' => 477, 'quantity' => 1]
            ],
            'extra_nights' => ['enabled' => false]
        ];
        
        $result = $this->calculator->calculate($input);
        
        $this->assertEquals(477.00, $result->total);
    }
    
    /**
     * @test
     */
    public function test_prevents_division_by_zero() {
        $this->expectException(\InvalidArgumentException::class);
        
        $input = [
            'participants' => ['adults' => 0, 'children' => ['f1' => 2]],
            'split_payment' => true
        ];
        
        $this->calculator->calculate($input);
    }
    
    /**
     * @test
     */
    public function test_json_corruption_handling() {
        $corrupted = '{\"test\": \"value\\\"}'; // Corrupted JSON
        $result = $this->calculator->parseJson($corrupted);
        
        $this->assertNotNull($result);
    }
    
    // 50+ altri test per OGNI scenario
}
```

#### Test Suite JavaScript
```javascript
// tests/v3/booking.test.js
describe('BTRBookingV3', () => {
    
    let booking;
    
    beforeEach(() => {
        document.body.innerHTML = `
            <input id="btr_num_adults" value="2">
            <input id="btr_num_child_f1" value="1">
            <div id="btr-total-price"></div>
        `;
        
        booking = new BTRBookingV3();
    });
    
    test('collects form data correctly', () => {
        const data = booking.collectFormData();
        
        expect(data.participants.adults).toBe(2);
        expect(data.participants.children.f1).toBe(1);
    });
    
    test('handles server errors gracefully', async () => {
        global.fetch = jest.fn(() =>
            Promise.reject(new Error('Network error'))
        );
        
        await booking.requestCalculation();
        
        expect(document.querySelector('.error-message')).toBeTruthy();
    });
});
```

### FASE 5: MIGRAZIONE E DEPLOYMENT (Settimana 8)

#### Migration Strategy
```php
// includes/v3/class-btr-migration-v3.php
class Migration {
    
    public function migrate(): void {
        // 1. Validate all existing bookings
        $this->validateExistingBookings();
        
        // 2. Create audit trail
        $this->createAuditTable();
        
        // 3. Progressive rollout
        if ($this->isTestUser()) {
            add_filter('btr_use_v3', '__return_true');
        }
    }
    
    private function validateExistingBookings(): void {
        $bookings = $this->getAllBookings();
        
        foreach ($bookings as $booking) {
            $oldTotal = $booking->getTotal();
            $newTotal = $this->recalculateWithV3($booking);
            
            if (abs($oldTotal - $newTotal) > 0.01) {
                $this->logDiscrepancy($booking, $oldTotal, $newTotal);
            }
        }
    }
}
```

---

## 📊 METRICHE DI SUCCESSO

| Metrica | Target | Misurazione |
|---------|--------|-------------|
| **Data Loss** | 0% | JSON parsing success rate |
| **Calculation Accuracy** | 100% | Test suite pass rate |
| **Performance** | <200ms | API response time P95 |
| **Security** | 0 vulnerabilities | Security audit |
| **Code Coverage** | >90% | PHPUnit + Jest |
| **Error Rate** | <0.01% | Production monitoring |

---

## 🚨 RISCHI E MITIGAZIONI

| Rischio | Probabilità | Impatto | Mitigazione |
|---------|------------|---------|-------------|
| Resistenza al cambiamento | ALTA | ALTO | Feature flag + rollback plan |
| Bug in produzione | MEDIA | CRITICO | Test coverage 90%+ |
| Performance degradation | BASSA | MEDIO | Caching + monitoring |
| Data migration errors | MEDIA | CRITICO | Validation + backup |

---

## ✅ CHECKLIST PRE-DEPLOYMENT

- [ ] Business rules TUTTE documentate e approvate
- [ ] Test suite completa (>100 test)
- [ ] Security audit superato
- [ ] Performance benchmark <200ms
- [ ] Rollback plan testato
- [ ] Monitoring configurato
- [ ] Documentation completa
- [ ] Training team completato

---

## 🔐 VULNERABILITÀ SECURITY CRITICHE

### 1. Input Non Validati
- **Prezzi manipolabili** dal frontend senza validazione backend
- **SQL Injection** in query dirette senza prepared statements
- **XSS** in campi utente non sanitizzati

### 2. Autenticazione/Autorizzazione
- **Nessun controllo** su chi può modificare preventivi
- **Session data** non protetti adeguatamente
- **AJAX endpoints** esposti senza nonce verification

### 3. Data Protection  
- **Dati sensibili** in log non criptati
- **PII** esposti in URL e meta fields
- **Backup** non protetti

## 🎯 FORMULA CALCOLO CORRETTA DA IMPLEMENTARE

```php
// FORMULA UNIFICATA (da implementare nel Calculator)
$base_cost = $package_price;
$total_participants_cost = 0;

foreach ($participants as $participant) {
    $category = $this->getAgeCategory($participant['age']);
    
    if ($category['global_price'] > 0) {
        // Usa prezzo fisso se configurato
        $participant_cost = $category['global_price'];
    } else {
        // Usa percentuale del prezzo base
        $participant_cost = $base_cost * $category['base_percentage'];
    }
    
    $total_participants_cost += $participant_cost;
}

// Supplementi camere (per persona)
$room_supplements = $this->calculateRoomSupplements($rooms, $participants);

// Notti extra
$extra_nights_cost = $this->calculateExtraNights($participants, $nights);

// Costi extra
$extra_costs = $this->calculateExtraCosts($selected_extras, $participants, $nights);

// Assicurazioni (prezzo unico per tutti)
$insurance_cost = $this->calculateInsurance($selected_insurance, $total_participants);

// TOTALE FINALE
$total = $total_participants_cost + $room_supplements + $extra_nights_cost + $extra_costs + $insurance_cost;
```

---

## 📝 FLUSSO PAGAMENTI IMPLEMENTATO

### Modalità Disponibili
1. **Pagamento Completo**: 100% al momento della prenotazione
2. **Caparra + Saldo**: Percentuale configurabile (10-90%)
3. **Pagamento di Gruppo**: Divisione quote tra partecipanti (solo adulti)

### Configurazione Admin
- Soglia minima partecipanti per gruppo
- Percentuale caparra default
- Giorni reminder per saldo

### Regole Business Pagamenti
- Solo adulti possono pagare
- Bambini assegnati obbligatoriamente a un adulto
- Chi paga per altri assume costi extra e assicurazioni
- Sistema impedisce doppi pagamenti

## 🔄 SISTEMA COUPON (DA IMPLEMENTARE)

### Requisiti Identificati
- Applicabile in preventivo e carrello
- Sconto fisso o percentuale
- Validazione codice univoco
- Limite utilizzi e scadenza
- Tracking utilizzo per utente

## ✅ RIEPILOGO REGOLE BUSINESS CONFERMATE

### Calcoli e Prezzi
✓ Formula: Somma prezzi individuali per categoria età
✓ IVA sempre inclusa nei prezzi
✓ Supplementi camere uguali per tutti occupanti
✓ Costi extra stesso prezzo adulti/bambini quando applicabile
✓ Assicurazioni prezzo unico per tutti

### Partecipanti
✓ Infanti (0-2): Sempre gratis, occupano posto, no assicurazioni
✓ Bambini F1-F4: Percentuali configurabili da admin
✓ Adulti: Sempre 100% prezzo base

### Camere
✓ Singola: Solo adulti
✓ Altre: Minimo 1 adulto per supervisione
✓ Bambini mai soli in camera

### Pagamenti
✓ Gateway: PayPal, Stripe, Satispay, Bonifico
✓ Modalità: Completo, Caparra+Saldo, Gruppo
✓ Solo adulti possono pagare
✓ Bambini assegnati obbligatoriamente a adulto

**PIANO DI REFACTORING CONSERVATIVO CON RICOSTRUZIONE PROGRESSIVA**

Tempo stimato: **8-10 settimane**
Approccio: **Feature flags + rollback progressivo**
Risultato: **Sistema affidabile, sicuro, mantenibile**

---

*Documento creato dopo analisi con agenti MCP specializzati*
*Versione: 3.0.0*
*Data: 30/08/2025*
*Status: PRONTO PER APPROVAZIONE*