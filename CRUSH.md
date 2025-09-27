# Born to Ride Booking - Development Guidelines

## Build/Test Commands
- Run single test: `vendor/bin/phpunit -c phpunit.xml tests/Integration/PreventivoTotalsSmokeTest.php`
- Run all tests: `vendor/bin/phpunit -c phpunit.xml`
- Quick lint: `php -l path/to/file.php`
- PHPCS (if configured): `phpcs --standard=WordPress wp-content/plugins/born-to-ride-booking`

## Code Style
- PHP: 4 spaces, WPCS, no trailing spaces
- Functions: `btr_foo_bar()` prefix
- Classes: `StudlyCaps`
- Files: kebab-case for plugin files
- Security: Use WP APIs (`sanitize_text_field`, `esc_html`, `wp_verify_nonce`), never interpolate SQL
- Database: Use `$wpdb->prepare()` for all queries

## Testing
- Test files: `tests/Unit/*Test.php` and `tests/Integration/*Test.php`
- Use in-memory meta store via `$GLOBALS['btr_test_meta']`
- Mock WP functions in `tests/bootstrap.php`
- Critical functions >80% coverage

## Naming & Conventions
- Translation functions: `__()`, `_e()` with consistent plugin domains
- Commit messages: `v1.0.234: breve descrizione del cambiamento`
- No sensitive data in version control
- GDPR compliant: no PII in logs, mask emails/phones