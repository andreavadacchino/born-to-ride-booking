<?php
/**
 * Gestione sicurezza e validazione per il sistema di pagamenti
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Security {
    
    /**
     * Valida dati piano di pagamento
     * 
     * @param array $data
     * @return array|WP_Error
     */
    public static function validate_payment_plan($data) {
        $errors = new WP_Error();
        
        // Valida preventivo ID
        if (empty($data['preventivo_id']) || !is_numeric($data['preventivo_id'])) {
            $errors->add('invalid_preventivo', __('ID preventivo non valido', 'born-to-ride-booking'));
        } else {
            $preventivo = get_post($data['preventivo_id']);
            if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
                $errors->add('preventivo_not_found', __('Preventivo non trovato', 'born-to-ride-booking'));
            }
        }
        
        // Valida tipo piano - supporta entrambi i nomi campo
        $valid_types = ['full', 'deposit_balance', 'group_split'];
        $plan_type = isset($data['plan_type']) ? $data['plan_type'] : (isset($data['payment_plan']) ? $data['payment_plan'] : '');
        if (empty($plan_type) || !in_array($plan_type, $valid_types)) {
            $errors->add('invalid_plan_type', __('Tipo di piano non valido', 'born-to-ride-booking'));
        }
        
        // Valida caparra per deposit_balance
        if ($plan_type === 'deposit_balance') {
            if (empty($data['deposit_percentage']) || $data['deposit_percentage'] < 10 || $data['deposit_percentage'] > 90) {
                $errors->add('invalid_deposit', __('Percentuale caparra deve essere tra 10% e 90%', 'born-to-ride-booking'));
            }
        }
        
        // Valida distribuzione gruppo
        if ($plan_type === 'group_split' && !empty($data['distribution'])) {
            $total_percentage = 0;
            foreach ($data['distribution'] as $participant) {
                if (!isset($participant['percentage']) || $participant['percentage'] < 0 || $participant['percentage'] > 100) {
                    $errors->add('invalid_distribution', __('Percentuali di distribuzione non valide', 'born-to-ride-booking'));
                    break;
                }
                $total_percentage += $participant['percentage'];
            }
            
            if (abs($total_percentage - 100) > 0.01) {
                $errors->add('distribution_sum', __('La somma delle percentuali deve essere 100%', 'born-to-ride-booking'));
            }
        }
        
        return $errors->has_errors() ? $errors : true;
    }
    
    /**
     * Valida link di pagamento
     * 
     * @param string $payment_hash
     * @return array|false
     */
    public static function validate_payment_link($payment_hash) {
        global $wpdb;
        
        if (empty($payment_hash) || !preg_match('/^[a-f0-9]{64}$/', $payment_hash)) {
            return false;
        }
        
        // Recupera pagamento
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments 
             WHERE payment_hash = %s 
             AND payment_status = 'pending'",
            $payment_hash
        ));
        
        if (!$payment) {
            return false;
        }
        
        // Verifica scadenza
        if (strtotime($payment->expires_at) < current_time('timestamp')) {
            return ['error' => 'expired'];
        }
        
        // Verifica preventivo esiste
        $preventivo = get_post($payment->preventivo_id);
        if (!$preventivo || $preventivo->post_status !== 'publish') {
            return ['error' => 'invalid_quote'];
        }
        
        return [
            'payment' => $payment,
            'preventivo' => $preventivo
        ];
    }
    
    /**
     * Valida dati checkout gruppo
     * 
     * @param array $data
     * @return array|WP_Error
     */
    public static function validate_group_checkout($data) {
        $errors = new WP_Error();
        
        // Valida payment hash
        if (empty($data['payment_hash'])) {
            $errors->add('missing_hash', __('Hash pagamento mancante', 'born-to-ride-booking'));
            return $errors;
        }
        
        $validation = self::validate_payment_link($data['payment_hash']);
        if (!$validation || isset($validation['error'])) {
            $error_message = __('Link di pagamento non valido', 'born-to-ride-booking');
            if (isset($validation['error']) && $validation['error'] === 'expired') {
                $error_message = __('Link di pagamento scaduto', 'born-to-ride-booking');
            }
            $errors->add('invalid_link', $error_message);
            return $errors;
        }
        
        // Valida nonce
        if (!isset($data['btr_payment_nonce']) || !wp_verify_nonce($data['btr_payment_nonce'], 'btr_group_payment_' . $data['payment_hash'])) {
            $errors->add('invalid_nonce', __('Sessione scaduta. Ricarica la pagina.', 'born-to-ride-booking'));
        }
        
        // FIX v1.0.242: Campi minimi per pagamenti gruppo
        $required_fields = [
            'billing_first_name' => __('Nome', 'born-to-ride-booking'),
            'billing_last_name' => __('Cognome', 'born-to-ride-booking'),
            'billing_email' => __('Email', 'born-to-ride-booking'),
        ];
        
        // Campi opzionali per pagamenti gruppo
        $optional_fields = [
            'billing_phone' => __('Telefono', 'born-to-ride-booking'),
            'billing_address' => __('Indirizzo', 'born-to-ride-booking'),
            'billing_city' => __('Città', 'born-to-ride-booking'),
            'billing_postcode' => __('CAP', 'born-to-ride-booking'),
            'billing_cf' => __('Codice Fiscale', 'born-to-ride-booking')
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors->add('missing_' . $field, sprintf(__('%s è obbligatorio', 'born-to-ride-booking'), $label));
            }
        }
        
        // Valida email
        if (!empty($data['billing_email']) && !is_email($data['billing_email'])) {
            $errors->add('invalid_email', __('Email non valida', 'born-to-ride-booking'));
        }
        
        // FIX v1.0.242: CF opzionale per pagamenti gruppo
        if (!empty($data['billing_cf']) && strlen($data['billing_cf']) > 0) {
            $cf = strtoupper($data['billing_cf']);
            // Validazione soft - accetta anche partite IVA
            if (strlen($cf) == 16 && !preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf)) {
                $errors->add('invalid_cf', __('Codice fiscale non valido', 'born-to-ride-booking'));
            }
        }
        
        // Valida CAP italiano
        if (!empty($data['billing_postcode']) && !preg_match('/^[0-9]{5}$/', $data['billing_postcode'])) {
            $errors->add('invalid_postcode', __('CAP non valido', 'born-to-ride-booking'));
        }
        
        // Valida metodo pagamento
        if (empty($data['payment_method'])) {
            $errors->add('missing_payment_method', __('Seleziona un metodo di pagamento', 'born-to-ride-booking'));
        } else {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (!isset($available_gateways[$data['payment_method']])) {
                $errors->add('invalid_payment_method', __('Metodo di pagamento non valido', 'born-to-ride-booking'));
            }
        }
        
        // Valida termini
        if (empty($data['terms'])) {
            $errors->add('terms_not_accepted', __('Devi accettare i termini e condizioni', 'born-to-ride-booking'));
        }
        
        return $errors->has_errors() ? $errors : $validation;
    }
    
    /**
     * Sanitizza dati input
     * 
     * @param array $data
     * @param array $fields_config
     * @return array
     */
    public static function sanitize_input($data, $fields_config = []) {
        $sanitized = [];
        
        $default_config = [
            'preventivo_id' => 'absint',
            'payment_id' => 'absint',
            'plan_type' => 'sanitize_key',
            'deposit_percentage' => 'absint',
            'payment_hash' => 'sanitize_text_field',
            'billing_first_name' => 'sanitize_text_field',
            'billing_last_name' => 'sanitize_text_field',
            'billing_email' => 'sanitize_email',
            'billing_phone' => 'sanitize_text_field',
            'billing_address' => 'sanitize_text_field',
            'billing_city' => 'sanitize_text_field',
            'billing_postcode' => 'sanitize_text_field',
            'billing_cf' => function($value) {
                return strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($value)));
            },
            'payment_method' => 'sanitize_key',
            'terms' => function($value) {
                return $value === 'on' || $value === '1' ? '1' : '';
            }
        ];
        
        $config = array_merge($default_config, $fields_config);
        
        foreach ($data as $key => $value) {
            if (isset($config[$key])) {
                if (is_callable($config[$key])) {
                    $sanitized[$key] = call_user_func($config[$key], $value);
                } else {
                    $sanitized[$key] = call_user_func($config[$key], $value);
                }
            } elseif (strpos($key, 'distribution') === 0) {
                // Gestione distribuzione gruppo
                if (is_array($value)) {
                    $sanitized[$key] = array_map(function($item) {
                        return [
                            'participant_id' => isset($item['participant_id']) ? absint($item['participant_id']) : 0,
                            'percentage' => isset($item['percentage']) ? floatval($item['percentage']) : 0
                        ];
                    }, $value);
                }
            } else {
                // Default: text field
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Genera hash sicuro per pagamento
     * 
     * @param int $payment_id
     * @param string $email
     * @return string
     */
    public static function generate_payment_hash($payment_id, $email) {
        $salt = wp_salt('secure_auth');
        $data = $payment_id . '|' . $email . '|' . time() . '|' . wp_rand();
        return hash_hmac('sha256', $data, $salt);
    }
    
    /**
     * Verifica rate limiting
     * 
     * @param string $action
     * @param string $identifier
     * @param int $max_attempts
     * @param int $window_seconds
     * @return bool
     */
    public static function check_rate_limit($action, $identifier, $max_attempts = 5, $window_seconds = 300) {
        $transient_key = 'btr_rate_limit_' . md5($action . '_' . $identifier);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            set_transient($transient_key, 1, $window_seconds);
            return true;
        }
        
        if ($attempts >= $max_attempts) {
            return false;
        }
        
        set_transient($transient_key, $attempts + 1, $window_seconds);
        return true;
    }
    
    /**
     * Valida permessi admin
     * 
     * @param string $action
     * @return bool
     */
    public static function validate_admin_permission($action) {
        $permissions = [
            'view_payments' => 'manage_options',
            'edit_payment' => 'manage_options',
            'send_reminder' => 'manage_options',
            'export_data' => 'manage_options',
            'manage_settings' => 'manage_options'
        ];
        
        $required_cap = isset($permissions[$action]) ? $permissions[$action] : 'manage_options';
        
        return current_user_can($required_cap);
    }
    
    /**
     * Crea log sicurezza
     * 
     * @param string $action
     * @param array $data
     * @param string $status
     */
    public static function log_security_event($action, $data = [], $status = 'info') {
        if (!defined('BTR_SECURITY_LOG') || !BTR_SECURITY_LOG) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'status' => $status,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'data' => $data
        ];
        
        // Rimuovi dati sensibili
        if (isset($log_entry['data']['billing_cf'])) {
            $log_entry['data']['billing_cf'] = substr($log_entry['data']['billing_cf'], 0, 6) . '***';
        }
        if (isset($log_entry['data']['billing_email'])) {
            $log_entry['data']['billing_email'] = self::mask_email($log_entry['data']['billing_email']);
        }
        
        // Scrivi log
        $log_file = WP_CONTENT_DIR . '/btr-security.log';
        $log_message = date('Y-m-d H:i:s') . ' - ' . json_encode($log_entry) . PHP_EOL;
        
        error_log($log_message, 3, $log_file);
    }
    
    /**
     * Ottieni IP client
     * 
     * @return string
     */
    public static function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Prendi primo IP se c'è una lista
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Maschera email per log
     * 
     * @param string $email
     * @return string
     */
    private static function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $masked_name = substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 3));
        
        return $masked_name . '@' . $domain;
    }
    
    /**
     * Valida CSRF token
     * 
     * @param string $token
     * @param string $action
     * @return bool
     */
    public static function validate_csrf_token($token, $action) {
        return wp_verify_nonce($token, 'btr_csrf_' . $action);
    }
    
    /**
     * Genera CSRF token
     * 
     * @param string $action
     * @return string
     */
    public static function generate_csrf_token($action) {
        return wp_create_nonce('btr_csrf_' . $action);
    }
    
    /**
     * Cripta dati sensibili
     * 
     * @param string $data
     * @return string
     */
    public static function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data);
        }
        
        $key = wp_salt('auth');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decripta dati
     * 
     * @param string $encrypted_data
     * @return string|false
     */
    public static function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data);
        }
        
        $data = base64_decode($encrypted_data);
        if ($data === false) {
            return false;
        }
        
        $key = wp_salt('auth');
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Verify webhook signature
     * 
     * @param array $webhook_data
     * @param string $signature
     * @return bool
     */
    public function verify_webhook_signature($webhook_data, $signature) {
        if (empty($signature)) {
            self::log_security_event('webhook_signature_missing', [
                'webhook_data' => $webhook_data
            ], 'warning');
            return false;
        }
        
        // Get webhook secret from options
        $webhook_secret = get_option('btr_webhook_secret', '');
        if (empty($webhook_secret)) {
            // If no secret configured, skip verification for now
            self::log_security_event('webhook_signature_skipped', [
                'reason' => 'no_secret_configured'
            ], 'warning');
            return true;
        }
        
        // Create expected signature
        $payload = is_array($webhook_data) ? json_encode($webhook_data) : $webhook_data;
        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
        
        // Compare signatures using hash_equals to prevent timing attacks
        $is_valid = hash_equals($expected_signature, $signature);
        
        if (!$is_valid) {
            self::log_security_event('webhook_signature_invalid', [
                'provided_signature' => $signature,
                'ip_address' => self::get_client_ip()
            ], 'error');
        }
        
        return $is_valid;
    }
}

// Helper functions globali

/**
 * Valida hash pagamento
 * 
 * @param string $hash
 * @return bool
 */
function btr_is_valid_payment_hash($hash) {
    return !empty($hash) && preg_match('/^[a-f0-9]{64}$/', $hash);
}