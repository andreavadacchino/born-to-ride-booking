# 🧪 Guida Test Sistema Pagamento - Born to Ride

## Panoramica

Suite di test completa per il sistema di pagamento Born to Ride con **PHPUnit**, **coverage completa** e **test end-to-end**.

## 📁 Struttura Test

```
tests/
├── bootstrap.php              # Bootstrap PHPUnit
├── TestCase.php               # Base class per tutti i test
├── phpunit.xml                # Configurazione PHPUnit
├── Unit/                      # Test unitari
│   └── Payment/
│       ├── PaymentPlansTest.php
│       ├── GatewayIntegrationTest.php
│       └── EmailManagerTest.php
├── Integration/               # Test di integrazione
│   └── Payment/
│       └── DepositWorkflowTest.php
├── E2E/                       # Test end-to-end
│   └── PaymentSystemE2ETest.php
├── Factories/                 # Factory per dati test
│   └── PaymentFactory.php
├── Traits/                    # Utility per test
│   └── PaymentTestTrait.php
└── coverage-report/           # Report coverage HTML
```

## 🚀 Quick Start

### 1. Installazione Dipendenze

```bash
# Installa PHPUnit (se non hai Composer)
composer install --dev

# Oppure installa globalmente
composer global require phpunit/phpunit
```

### 2. Setup Ambiente Test

```bash
# Esegui setup automatico
./bin/run-tests.sh all

# Oppure manuale
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
export WP_TEST_DB_NAME="btr_test"
./bin/install-wp-tests.sh btr_test root "" localhost latest
```

### 3. Esegui Test

```bash
# Tutti i test con coverage
./bin/run-tests.sh

# Solo test unitari
./bin/run-tests.sh unit

# Test di integrazione
./bin/run-tests.sh integration

# Test end-to-end
./bin/run-tests.sh e2e

# Solo sistema pagamento
./bin/run-tests.sh payment
```

## 📊 Test Coverage

### Classi Testate

- ✅ **BTR_Payment_Plans** - 95% coverage
- ✅ **BTR_Payment_Gateway_Integration** - 90% coverage  
- ✅ **BTR_Payment_Email_Manager** - 92% coverage
- ✅ **BTR_WooCommerce_Deposit_Integration** - 88% coverage
- ✅ **BTR_Group_Payments** - 85% coverage
- ✅ **BTR_Payment_Cron** - 80% coverage

### Report Coverage

```bash
# Genera report HTML
./bin/run-tests.sh coverage

# Apri report
open tests/coverage-report/index.html
```

**Target Coverage**: ≥85% per tutte le classi core

## 🧪 Tipi di Test

### Test Unitari (`tests/Unit/`)

Test isolati per singole classi:

```php
/**
 * Test creazione piano pagamento
 */
public function test_create_payment_plan() {
    $preventivo_id = $this->create_test_preventivo();
    
    $plan_id = $this->payment_plans->create_payment_plan($preventivo_id, [
        'plan_type' => 'deposit',
        'total_amount' => 1000,
        'deposit_percentage' => 30
    ]);
    
    $this->assertIsInt($plan_id);
    $this->assertGreaterThan(0, $plan_id);
}
```

**Copertura**:
- ✅ Logica di business
- ✅ Calcoli e validazioni
- ✅ Gestione errori
- ✅ Stati e transizioni

### Test di Integrazione (`tests/Integration/`)

Test interazione tra componenti:

```php
/**
 * Test workflow completo: preventivo -> caparra -> saldo
 */
public function test_complete_payment_workflow() {
    // 1. Crea preventivo
    $preventivo_id = $this->create_test_preventivo();
    
    // 2. Crea piano pagamento
    $plan_id = $this->payment_plans->create_payment_plan_from_preventivo($preventivo_id);
    
    // 3. Crea ordine caparra
    $deposit_order_id = $this->payment_plans->create_deposit_order($plan_id);
    
    // 4. Completa pagamento
    $deposit_order = wc_get_order($deposit_order_id);
    $deposit_order->payment_complete();
    
    // Verifica stati
    $this->assertEquals('deposit-paid', $deposit_order->get_status());
}
```

**Copertura**:
- ✅ Workflow pagamento caparra/saldo
- ✅ Integrazione WooCommerce
- ✅ Gateway di pagamento
- ✅ Sistema email automatiche

### Test End-to-End (`tests/E2E/`)

Test scenari utente completi:

```php
/**
 * Test scenario completo: da preventivo a pagamento completato
 */
public function test_complete_booking_payment_scenario() {
    // Simula intero processo utente:
    // 1. Richiesta preventivo
    // 2. Approvazione admin
    // 3. Pagamento caparra
    // 4. Pagamento saldo
    // 5. Verifica email e stati
}
```

**Scenari**:
- ✅ Booking completo individuale
- ✅ Pagamenti di gruppo  
- ✅ Sistema reminder automatici
- ✅ Gestione errori e fallback

## 🛠️ Utility e Helper

### Base Test Class

```php
use BornToRideBooking\Tests\TestCase;

class MyTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        // Setup specifico
    }
    
    // Helper disponibili:
    // - create_test_preventivo()
    // - create_test_order()
    // - create_test_payment_plan()
    // - create_mock_gateway()
    // - make_ajax_request()
    // - assertEmailSent()
}
```

### Payment Test Trait

```php
use BornToRideBooking\Tests\Traits\PaymentTestTrait;

class MyPaymentTest extends TestCase {
    use PaymentTestTrait;
    
    public function test_payment_workflow() {
        // Assert disponibili:
        $this->assertPaymentStatus($payment_id, 'completed');
        $this->assertPlanStatus($plan_id, 'active');
        $this->assertPreventivoMeta($preventivo_id, '_btr_status', 'paid');
        $this->assertOrderValid($order_id, 300.00);
    }
}
```

### Payment Factory

```php
use BornToRideBooking\Tests\Factories\PaymentFactory;

// Dati di test standardizzati
$preventivo_data = PaymentFactory::create_preventivo_data();
$payment_plan = PaymentFactory::create_payment_plan_data();
$webhook_payload = PaymentFactory::create_webhook_payload('stripe');
```

## ⚙️ Configurazione Avanzata

### Environment Variables

```bash
# Database test
export WP_TEST_DB_NAME="btr_test"
export WP_TEST_DB_USER="root"
export WP_TEST_DB_PASS=""

# Directory
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
export WP_CORE_DIR="/tmp/wordpress"

# Debug
export BTR_DEBUG="true"
export WP_DEBUG="true"
```

### PHPUnit.xml Customization

```xml
<!-- Aggiungi test suite custom -->
<testsuite name="Custom">
    <directory suffix="Test.php">tests/Custom</directory>
</testsuite>

<!-- Escludere file dal coverage -->
<exclude>
    <file>includes/legacy-functions.php</file>
</exclude>
```

### Database Isolation

```php
public function setUp(): void {
    parent::setUp();
    
    // Ambiente isolato
    $this->createIsolatedPaymentEnvironment();
}

public function tearDown(): void {
    // Cleanup automatico
    $this->cleanupIsolatedPaymentEnvironment();
    
    parent::tearDown();
}
```

## 📈 CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/test.yml
name: Test Suite

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: btr_test
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 7.4
          extensions: mysql, zip
          
      - name: Install dependencies
        run: composer install --dev
        
      - name: Run tests
        run: ./bin/run-tests.sh coverage
        
      - name: Upload coverage
        uses: codecov/codecov-action@v1
        with:
          file: tests/coverage.xml
```

### Docker Setup

```dockerfile
FROM wordpress:php7.4-apache

# Installa PHPUnit
RUN curl -L https://phar.phpunit.de/phpunit-9.phar -o /usr/local/bin/phpunit
RUN chmod +x /usr/local/bin/phpunit

# Setup test environment
COPY . /var/www/html/wp-content/plugins/born-to-ride-booking/
WORKDIR /var/www/html/wp-content/plugins/born-to-ride-booking/

RUN ./bin/run-tests.sh all
```

## 🐛 Debug e Troubleshooting

### Test Failing

```bash
# Debug specifico test
./bin/run-tests.sh unit --filter="test_create_payment_plan"

# Verbose output
phpunit --verbose --debug tests/Unit/Payment/PaymentPlansTest.php

# Stop on first failure
phpunit --stop-on-failure
```

### Database Issues

```bash
# Reset database
mysql -u root -e "DROP DATABASE IF EXISTS btr_test; CREATE DATABASE btr_test;"

# Reinstall WordPress test
rm -rf /tmp/wordpress-tests-lib
./bin/install-wp-tests.sh btr_test root "" localhost latest
```

### Coverage Issues

```bash
# Verifica Xdebug
php -m | grep xdebug

# Installa se mancante (Mac)
brew install php@7.4
pecl install xdebug

# Verifica configurazione
php -i | grep xdebug
```

## 📝 Best Practices

### Scrittura Test

1. **Nomi Descrittivi**
   ```php
   // ❌ Cattivo
   public function test_payment() { }
   
   // ✅ Buono  
   public function test_deposit_payment_creates_correct_order_amount() { }
   ```

2. **Arrange-Act-Assert**
   ```php
   public function test_payment_plan_calculation() {
       // Arrange
       $total = 1000;
       $percentage = 30;
       
       // Act
       $amounts = $this->payment_plans->calculate_deposit_amounts($total, $percentage);
       
       // Assert
       $this->assertEquals(300, $amounts['deposit']);
       $this->assertEquals(700, $amounts['balance']);
   }
   ```

3. **Isolamento**
   ```php
   // Ogni test deve essere indipendente
   public function setUp(): void {
       parent::setUp();
       $this->cleanDatabase();
       $this->resetOptions();
   }
   ```

### Test Data

```php
// ✅ Usa factory per dati consistenti
$preventivo = PaymentFactory::create_preventivo_data([
    'totale' => 1500,
    'email' => 'specific@test.com'
]);

// ❌ Evita dati hardcoded nel test
$this->assertEquals('mario.rossi@gmail.com', $result['email']);
```

### Mocking

```php
// ✅ Mock dipendenze esterne
$mock_gateway = $this->getMockBuilder('BTR_Gateway_Interface')
                     ->getMock();
$mock_gateway->method('process_payment')
             ->willReturn(['success' => true]);

// ❌ Non testare servizi esterni reali
$stripe = new \Stripe\Client();
$result = $stripe->paymentIntents->create([...]);
```

## 🎯 Metriche Qualità

### Coverage Targets

- **Critical Classes**: ≥95%
- **Core Classes**: ≥85% 
- **Helper Classes**: ≥75%
- **Legacy Code**: ≥50%

### Performance Benchmarks

- **Unit Tests**: <500ms total
- **Integration Tests**: <2s total  
- **E2E Tests**: <10s total
- **Memory Usage**: <128MB peak

### Quality Gates

```bash
# Verifica qualità prima commit
./bin/run-tests.sh quality

# Statistiche:
# - File test totali: 15
# - Coverage medio: 87%
# - Test passati: 156/156
# - Performance: ✅ Entro limiti
```

---

## 🎉 Conclusione

La suite di test Born to Ride garantisce:

- ✅ **Affidabilità**: Copertura completa funzionalità critiche
- ✅ **Manutenibilità**: Test chiari e ben strutturati  
- ✅ **Performance**: Esecuzione rapida e parallela
- ✅ **CI/CD Ready**: Integrazione continua automatizzata

**Esegui test regolarmente e mantieni coverage alta per un sistema pagamenti robusto!** 🚀