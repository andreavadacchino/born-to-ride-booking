<?php
/**
 * XSS Prevention Security Tests
 *
 * @package BornToRideBooking\Tests\Security
 */

namespace BornToRideBooking\Tests\Security;

use BornToRideBooking\Tests\IntegrationTestCase;

/**
 * Test XSS prevention across the plugin
 */
class XSSPreventionTest extends IntegrationTestCase {

    /**
     * Common XSS payloads for testing
     */
    private $xss_payloads = [
        '<script>alert("XSS")</script>',
        '"><script>alert(String.fromCharCode(88,83,83))</script>',
        '<img src=x onerror=alert("XSS")>',
        '<svg/onload=alert("XSS")>',
        'javascript:alert("XSS")',
        '<iframe src=javascript:alert("XSS")>',
        '<body onload=alert("XSS")>',
        '<<SCRIPT>alert("XSS");//<</SCRIPT>',
        '<script src=http://evil.com/xss.js></script>',
        '&#60;script&#62;alert("XSS")&#60;/script&#62;',
        '<div style="background:url(javascript:alert(1))">',
        '<input type="text" onfocus="alert(1)">',
        '<a href="javascript:void(0)" onclick="alert(1)">Click</a>',
        '"><img src=x onerror=prompt(1)>',
        '<script>document.cookie</script>'
    ];

    /**
     * Test anagrafici form fields XSS prevention
     */
    public function test_anagrafici_fields_xss_prevention() {
        $preventivo_id = $this->createTestPreventivo();

        foreach ($this->xss_payloads as $payload) {
            $anagrafici_data = [
                [
                    'nome' => $payload,
                    'cognome' => $payload,
                    'eta' => '25',
                    'email' => 'test@example.com',
                    'telefono' => $payload,
                    'indirizzo' => $payload,
                    'note' => $payload
                ]
            ];

            // Save anagrafici with XSS payload
            update_post_meta($preventivo_id, '_anagrafici_preventivo', serialize($anagrafici_data));

            // Retrieve and check sanitization
            $saved_data = maybe_unserialize(get_post_meta($preventivo_id, '_anagrafici_preventivo', true));

            if (is_array($saved_data) && !empty($saved_data[0])) {
                $person = $saved_data[0];

                // Verify no script tags remain
                $this->assertStringNotContainsString('<script', $person['nome']);
                $this->assertStringNotContainsString('<script', $person['cognome']);
                $this->assertStringNotContainsString('javascript:', $person['telefono']);
                $this->assertStringNotContainsString('onerror=', $person['indirizzo']);
                $this->assertStringNotContainsString('onclick=', $person['note']);
            }
        }
    }

    /**
     * Test payment form XSS prevention
     */
    public function test_payment_form_xss_prevention() {
        global $wpdb;

        foreach ($this->xss_payloads as $payload) {
            $payment_data = [
                'preventivo_id' => 123,
                'payer_email' => 'test@example.com',
                'payer_name' => $payload,
                'payment_note' => $payload,
                'amount' => 100.00
            ];

            // Simulate saving payment data
            $sanitized_data = [
                'preventivo_id' => absint($payment_data['preventivo_id']),
                'payer_email' => sanitize_email($payment_data['payer_email']),
                'payer_name' => sanitize_text_field($payment_data['payer_name']),
                'payment_note' => sanitize_textarea_field($payment_data['payment_note']),
                'amount' => floatval($payment_data['amount'])
            ];

            // Verify XSS payloads are sanitized
            $this->assertStringNotContainsString('<script', $sanitized_data['payer_name']);
            $this->assertStringNotContainsString('javascript:', $sanitized_data['payment_note']);
            $this->assertStringNotContainsString('onerror=', $sanitized_data['payer_name']);
        }
    }

    /**
     * Test search input XSS prevention
     */
    public function test_search_input_xss() {
        foreach ($this->xss_payloads as $payload) {
            $_GET['search'] = $payload;
            $_POST['search'] = $payload;

            // Test sanitization methods
            $sanitized_get = sanitize_text_field($_GET['search']);
            $sanitized_post = sanitize_text_field($_POST['search']);

            // Verify dangerous content removed
            $this->assertStringNotContainsString('<script', $sanitized_get);
            $this->assertStringNotContainsString('<script', $sanitized_post);
            $this->assertStringNotContainsString('javascript:', $sanitized_get);
            $this->assertStringNotContainsString('onerror=', $sanitized_post);
        }
    }

    /**
     * Test HTML output escaping
     */
    public function test_html_output_escaping() {
        $dangerous_content = [
            'title' => '<script>alert("XSS")</script>Test Title',
            'description' => 'Test <img src=x onerror=alert(1)> Description',
            'price' => '100<script>alert(1)</script>',
            'url' => 'javascript:alert("XSS")'
        ];

        // Test various escaping functions
        $escaped_title = esc_html($dangerous_content['title']);
        $escaped_attr = esc_attr($dangerous_content['description']);
        $escaped_url = esc_url($dangerous_content['url']);
        $escaped_js = esc_js($dangerous_content['price']);

        // Verify script tags are escaped
        $this->assertStringNotContainsString('<script>', $escaped_title);
        $this->assertStringContainsString('&lt;script&gt;', $escaped_title);

        $this->assertStringNotContainsString('onerror=', $escaped_attr);
        $this->assertStringNotContainsString('javascript:', $escaped_url);
        $this->assertStringNotContainsString('<script>', $escaped_js);
    }

    /**
     * Test JSON output XSS prevention
     */
    public function test_json_output_xss() {
        foreach ($this->xss_payloads as $payload) {
            $data = [
                'status' => 'success',
                'message' => $payload,
                'data' => [
                    'name' => $payload,
                    'value' => $payload
                ]
            ];

            // JSON encode with proper escaping
            $json = wp_json_encode($data);

            // Decode to verify structure preserved
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);

            // Verify dangerous characters are escaped in JSON
            $this->assertStringNotContainsString('<script>', $json);
            $this->assertStringNotContainsString('</script>', $json);

            // When output in HTML context, should be escaped
            $html_safe = esc_attr($json);
            $this->assertStringNotContainsString('onerror=', $html_safe);
        }
    }

    /**
     * Test rich text editor content sanitization
     */
    public function test_rich_text_content_sanitization() {
        $rich_content_with_xss = [
            '<p>Normal text</p><script>alert("XSS")</script>',
            '<div onclick="alert(1)">Click me</div>',
            '<a href="javascript:alert(1)">Link</a>',
            '<img src="valid.jpg" onerror="alert(1)">',
            '<style>body { background: url(javascript:alert(1)) }</style>'
        ];

        foreach ($rich_content_with_xss as $content) {
            // Test wp_kses with allowed tags
            $allowed_html = [
                'p' => [],
                'a' => ['href' => [], 'title' => []],
                'div' => ['class' => [], 'id' => []],
                'img' => ['src' => [], 'alt' => []],
                'strong' => [],
                'em' => []
            ];

            $sanitized = wp_kses($content, $allowed_html);

            // Verify dangerous attributes/tags removed
            $this->assertStringNotContainsString('<script', $sanitized);
            $this->assertStringNotContainsString('onclick=', $sanitized);
            $this->assertStringNotContainsString('onerror=', $sanitized);
            $this->assertStringNotContainsString('javascript:', $sanitized);
            $this->assertStringNotContainsString('<style', $sanitized);
        }
    }

    /**
     * Test URL parameter XSS prevention
     */
    public function test_url_parameter_xss() {
        $xss_urls = [
            'http://example.com/<script>alert(1)</script>',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:alert(1)',
            '//evil.com/xss.js'
        ];

        foreach ($xss_urls as $url) {
            $_GET['redirect_to'] = $url;

            // Test URL sanitization
            $safe_url = esc_url_raw($_GET['redirect_to']);

            // For redirects, use wp_safe_redirect allowed hosts
            $allowed_hosts = ['example.com', 'btr.labuix.com'];
            $parsed_url = wp_parse_url($safe_url);

            if (isset($parsed_url['host']) && !in_array($parsed_url['host'], $allowed_hosts)) {
                $safe_url = home_url();
            }

            // Verify dangerous protocols removed
            $this->assertStringNotContainsString('javascript:', $safe_url);
            $this->assertStringNotContainsString('vbscript:', $safe_url);
            $this->assertStringNotContainsString('data:', $safe_url);
        }
    }

    /**
     * Test file upload filename XSS prevention
     */
    public function test_file_upload_xss() {
        $malicious_filenames = [
            'test<script>alert(1)</script>.pdf',
            'file";alert(1);//.jpg',
            '../../../etc/passwd',
            'file.php.jpg',
            'file.asp;.jpg'
        ];

        foreach ($malicious_filenames as $filename) {
            // Test filename sanitization
            $safe_filename = sanitize_file_name($filename);

            // Verify dangerous characters removed
            $this->assertStringNotContainsString('<script>', $safe_filename);
            $this->assertStringNotContainsString('"', $safe_filename);
            $this->assertStringNotContainsString('../', $safe_filename);
            $this->assertStringNotContainsString(';', $safe_filename);

            // Verify double extensions handled
            $this->assertStringNotContainsString('.php.', $safe_filename);
        }
    }

    /**
     * Test email content XSS prevention
     */
    public function test_email_content_xss() {
        foreach ($this->xss_payloads as $payload) {
            $email_data = [
                'to' => 'test@example.com',
                'subject' => $payload,
                'message' => "Hello {$payload}",
                'headers' => ["Reply-To: {$payload}"]
            ];

            // Sanitize email content
            $safe_subject = sanitize_text_field($email_data['subject']);
            $safe_message = wp_kses_post($email_data['message']);

            // Verify XSS removed
            $this->assertStringNotContainsString('<script', $safe_subject);
            $this->assertStringNotContainsString('<script', $safe_message);
            $this->assertStringNotContainsString('javascript:', $safe_subject);
        }
    }

    /**
     * Test stored XSS prevention in database
     */
    public function test_stored_xss_prevention() {
        $preventivo_id = $this->createTestPreventivo();

        foreach ($this->xss_payloads as $payload) {
            // Attempt to store XSS in various meta fields
            update_post_meta($preventivo_id, '_note_admin', sanitize_textarea_field($payload));
            update_post_meta($preventivo_id, '_custom_field', sanitize_text_field($payload));

            // Retrieve and verify sanitization
            $note = get_post_meta($preventivo_id, '_note_admin', true);
            $custom = get_post_meta($preventivo_id, '_custom_field', true);

            $this->assertStringNotContainsString('<script', $note);
            $this->assertStringNotContainsString('<script', $custom);
            $this->assertStringNotContainsString('javascript:', $note);
            $this->assertStringNotContainsString('onerror=', $custom);
        }
    }
}