<?php
declare(strict_types=1);

error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Minimal WordPress helpers -------------------------------------------------
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []): array
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (!is_array($args)) {
            parse_str((string) $args, $args);
        }

        return array_merge($defaults, $args ?? []);
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data)
    {
        if (!is_string($data)) {
            return $data;
        }

        $result = @unserialize($data);
        if ($result === false && $data !== 'b:0;') {
            return $data;
        }

        return $result;
    }
}

if (!function_exists('btr_debug_log')) {
    function btr_debug_log(string $message): void
    {
        // In testing manteniamo silenzioso ma lasciamo un hook per eventuali debug
    }
}

if (!function_exists('add_action')) {
    function add_action(...$args): void {}
}

if (!function_exists('add_filter')) {
    function add_filter(...$args): void {}
}

if (!function_exists('WC')) {
    function WC()
    {
        return $GLOBALS['woocommerce'] ?? null;
    }
}

if (!function_exists('do_action')) {
    function do_action(...$args): void {}
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

// In-memory meta store usato dai test --------------------------------------
$GLOBALS['btr_test_meta'] = [];

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = true)
    {
        $store = $GLOBALS['btr_test_meta'][$post_id][$key] ?? null;
        if ($single) {
            return $store ?? '';
        }
        return is_array($store) ? $store : [$store];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $key, $value): void
    {
        $GLOBALS['btr_test_meta'][$post_id][$key] = $value;
    }
}

// Carica esclusivamente i file necessari ai test smoke ----------------------
require_once __DIR__ . '/../includes/class-btr-price-calculator.php';
require_once __DIR__ . '/../includes/helpers/btr-meta-helpers.php';
require_once __DIR__ . '/../includes/class-btr-unified-calculator.php';
