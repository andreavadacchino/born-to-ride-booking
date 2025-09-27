# Born to Ride Booking - Test Suite Documentation

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+
- Composer
- MySQL/MariaDB
- Node.js 18+ (for JavaScript tests)
- WordPress Test Suite

### Installation

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Setup WordPress test environment
bash bin/install-wp-tests.sh wordpress_test root root localhost latest
```

## 🧪 Running Tests

### All Tests
```bash
composer test:all
```

### Unit Tests
```bash
composer test:unit
# or
vendor/bin/phpunit --testsuite=unit
```

### Integration Tests
```bash
composer test:integration
# or
vendor/bin/phpunit --testsuite=integration
```

### Security Tests
```bash
composer test:security
# or
vendor/bin/phpunit --testsuite=security
```

### Performance Tests
```bash
composer test:performance
```

### JavaScript Tests
```bash
npm test
# Watch mode
npm run test:watch
# Coverage
npm run test:coverage
```

### Code Quality
```bash
# PHP CodeSniffer
composer cs

# PHPStan
composer analyse

# ESLint
npm run lint:js
```

## 📊 Coverage Reports

### PHP Coverage
```bash
composer test:coverage
# View HTML report at coverage/html/index.html
```

### JavaScript Coverage
```bash
npm run test:coverage
```

## 🏗️ Test Structure

```
tests/
├── Unit/                    # Unit tests (isolated component testing)
│   ├── BTRPriceCalculatorTest.php
│   ├── BTRGroupPaymentsTest.php
│   └── ...
├── Integration/             # WordPress integration tests
│   ├── WordPressHooksTest.php
│   ├── WooCommerceIntegrationTest.php
│   └── ...
├── Functional/              # Business logic tests
│   ├── BookingFlowTest.php
│   └── ...
├── Security/                # Security vulnerability tests
│   ├── SQLInjectionTest.php
│   ├── XSSPreventionTest.php
│   ├── CSRFProtectionTest.php
│   └── ...
├── Performance/             # Performance benchmarks
│   └── ...
├── E2E/                     # End-to-end tests (Playwright)
│   └── ...
├── JavaScript/              # JavaScript unit tests
│   └── ...
├── fixtures/                # Test data
├── mocks/                   # Mock objects
├── TestCase.php            # Base unit test class
├── IntegrationTestCase.php # Base integration test class
└── bootstrap.php           # Test environment setup
```

## 🎯 Test Coverage Goals

- **Unit Tests**: 85%+ coverage
- **Integration Tests**: 90%+ for critical paths
- **Security Tests**: 100% OWASP compliance
- **Performance Tests**: <200ms API response time
- **JavaScript Tests**: 80%+ coverage

## 🔐 Security Tests

Our security test suite covers:
- **SQL Injection Prevention**: All database queries
- **XSS Prevention**: Input sanitization and output escaping
- **CSRF Protection**: Nonce verification and token validation
- **Authentication**: User capability checks
- **Authorization**: Access control validation
- **Input Validation**: Data sanitization

## 🚦 CI/CD Integration

Tests run automatically on:
- Every push to `main` and `develop` branches
- Every pull request
- Weekly schedule (Sunday midnight)

### GitHub Actions Matrix
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2
- **WordPress Versions**: 5.9, 6.0, 6.1, 6.2, 6.3, 6.4, latest
- **Browsers**: Chrome, Firefox, Safari (E2E tests)

## 📝 Writing New Tests

### Unit Test Example
```php
class MyComponentTest extends \BornToRideBooking\Tests\TestCase {
    public function test_something() {
        // Arrange
        $component = new MyComponent();

        // Act
        $result = $component->doSomething();

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Example
```php
class MyIntegrationTest extends \BornToRideBooking\Tests\IntegrationTestCase {
    public function test_wordpress_integration() {
        // Create test data
        $post_id = $this->createTestPreventivo();

        // Test functionality
        $result = do_something_with_post($post_id);

        // Assert
        $this->assertNotFalse($result);
    }
}
```

## 🐛 Debugging Tests

```bash
# Run specific test file
vendor/bin/phpunit tests/Unit/BTRPriceCalculatorTest.php

# Run specific test method
vendor/bin/phpunit --filter test_calculate_extra_costs

# Verbose output
vendor/bin/phpunit --verbose

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

## 📚 Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Documentation](https://make.wordpress.org/core/handbook/testing/)
- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [Playwright Documentation](https://playwright.dev/docs/intro)

## 🤝 Contributing

1. Write tests for new features
2. Ensure all tests pass before submitting PR
3. Maintain or improve code coverage
4. Follow existing test patterns and conventions
5. Document complex test scenarios

## 📄 License

GPL-2.0-or-later