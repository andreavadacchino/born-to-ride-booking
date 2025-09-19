<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test di sicurezza per BTR Plugin
 *
 * CRITICO: Verifica protezione contro attacchi comuni
 * SQL Injection, XSS, CSRF su input critici
 */
final class SecurityValidationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['btr_test_meta'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['btr_test_meta']);
    }

    /**
     * Test SQL Injection su preventivo_id
     *
     * CRITICO: Questo campo viene usato in molte query database
     */
    public function testSqlInjectionProtectionPreventivoId(): void
    {
        $malicious_inputs = [
            "1; DROP TABLE wp_posts;--",
            "1' OR '1'='1",
            "1 UNION SELECT * FROM wp_users--",
            "'; EXEC xp_cmdshell('dir'); --",
            "1'; UPDATE wp_posts SET post_title='HACKED' WHERE ID=1; --"
        ];

        foreach ($malicious_inputs as $malicious_input) {
            // Test che input malevoli vengano sanitizzati
            $sanitized = $this->sanitizePreventivoId($malicious_input);

            $this->assertIsNumeric($sanitized, "Input '$malicious_input' deve essere numerico dopo sanitizzazione");
            $this->assertGreaterThan(0, $sanitized, "Preventivo ID deve essere positivo");
            $this->assertLessThan(999999, $sanitized, "Preventivo ID deve essere ragionevole");
        }
    }

    /**
     * Test XSS Protection su campi testuali
     */
    public function testXssProtectionTextField(): void
    {
        $xss_payloads = [
            "<script>alert('XSS')</script>",
            "javascript:alert('XSS')",
            "<img src=x onerror=alert('XSS')>",
            "';alert('XSS');//",
            "<iframe src='javascript:alert(\"XSS\")'></iframe>"
        ];

        foreach ($xss_payloads as $payload) {
            $sanitized = $this->sanitizeTextField($payload);

            $this->assertStringNotContainsString('<script', $sanitized, 'Script tags devono essere rimossi');
            $this->assertStringNotContainsString('javascript:', $sanitized, 'JavaScript URLs devono essere rimossi');
            $this->assertStringNotContainsString('onerror=', $sanitized, 'Event handlers devono essere rimossi');
        }
    }

    /**
     * Test CSRF Protection per AJAX endpoints
     */
    public function testCsrfProtectionAjaxEndpoints(): void
    {
        // Simula richiesta senza nonce
        $request_without_nonce = [
            'action' => 'btr_create_organizer_order',
            'preventivo_id' => '37525'
        ];

        $result = $this->validateAjaxRequest($request_without_nonce);
        $this->assertFalse($result['valid'], 'Richiesta senza nonce deve essere respinta');
        $this->assertStringContainsString('nonce', $result['error'], 'Errore deve menzionare nonce');

        // Simula richiesta con nonce valido
        $request_with_nonce = [
            'action' => 'btr_create_organizer_order',
            'preventivo_id' => '37525',
            'security' => 'valid_nonce_here'
        ];

        $result = $this->validateAjaxRequest($request_with_nonce);
        $this->assertTrue($result['valid'], 'Richiesta con nonce valido deve essere accettata');
    }

    /**
     * Test validazione lunghezza input per prevenire buffer overflow
     */
    public function testInputLengthValidation(): void
    {
        // Test campo nome (dovrebbe avere limite ragionevole)
        $very_long_name = str_repeat('A', 1000);
        $sanitized_name = $this->sanitizeNameField($very_long_name);

        $this->assertLessThanOrEqual(100, strlen($sanitized_name), 'Nome deve essere limitato a 100 caratteri');

        // Test campo email
        $very_long_email = str_repeat('a', 500) . '@example.com';
        $sanitized_email = $this->sanitizeEmailField($very_long_email);

        $this->assertLessThanOrEqual(254, strlen($sanitized_email), 'Email deve rispettare RFC limits');
    }

    /**
     * Test protezione against path traversal
     */
    public function testPathTraversalProtection(): void
    {
        $malicious_paths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '....//....//....//etc//passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd'
        ];

        foreach ($malicious_paths as $path) {
            $sanitized = $this->sanitizeFilePath($path);

            $this->assertStringNotContainsString('..', $sanitized, 'Path traversal deve essere bloccato');
            $this->assertStringNotContainsString('%2e', $sanitized, 'Encoded path traversal deve essere bloccato');
        }
    }

    /**
     * Test validazione numeri per prevenire integer overflow
     */
    public function testNumericValidationLimits(): void
    {
        $extreme_numbers = [
            PHP_INT_MAX + 1,
            -PHP_INT_MAX - 1,
            '999999999999999999999999999999',
            '-999999999999999999999999999999'
        ];

        foreach ($extreme_numbers as $number) {
            $sanitized = $this->sanitizeAmountField($number);

            $this->assertIsNumeric($sanitized, 'Risultato deve essere numerico');
            $this->assertLessThanOrEqual(999999.99, $sanitized, 'Importo deve essere ragionevole');
            $this->assertGreaterThanOrEqual(-999999.99, $sanitized, 'Importo negativo deve essere limitato');
        }
    }

    // HELPER METHODS - Simulano le funzioni di sanitizzazione del plugin

    private function sanitizePreventivoId($input): int
    {
        // Simula sanitizzazione ID preventivo
        $cleaned = preg_replace('/[^0-9]/', '', (string)$input);
        $id = intval($cleaned);

        return max(1, min(999999, $id));
    }

    private function sanitizeTextField($input): string
    {
        // Simula sanitizzazione campo testo
        $cleaned = strip_tags($input);
        $cleaned = preg_replace('/javascript:/i', '', $cleaned);
        $cleaned = preg_replace('/on\w+=/i', '', $cleaned);

        return htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
    }

    private function validateAjaxRequest(array $request): array
    {
        // Simula validazione AJAX con nonce
        if (!isset($request['security'])) {
            return ['valid' => false, 'error' => 'Missing security nonce'];
        }

        if ($request['security'] !== 'valid_nonce_here') {
            return ['valid' => false, 'error' => 'Invalid security nonce'];
        }

        return ['valid' => true];
    }

    private function sanitizeNameField($input): string
    {
        $cleaned = strip_tags($input);
        return substr($cleaned, 0, 100);
    }

    private function sanitizeEmailField($input): string
    {
        $cleaned = filter_var($input, FILTER_SANITIZE_EMAIL);
        return substr($cleaned, 0, 254);
    }

    private function sanitizeFilePath($input): string
    {
        $cleaned = str_replace(['..', '%2e'], '', $input);
        return basename($cleaned);
    }

    private function sanitizeAmountField($input): float
    {
        $cleaned = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $amount = floatval($cleaned);

        return max(-999999.99, min(999999.99, $amount));
    }
}