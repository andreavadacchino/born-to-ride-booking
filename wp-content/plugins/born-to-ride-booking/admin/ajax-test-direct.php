<?php
/**
 * Test diretto delle funzioni di suggerimento modifiche
 */

// Abilita error reporting per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definisci le costanti necessarie per far funzionare le funzioni
if (!defined('BTR_PLUGIN_DIR')) {
    define('BTR_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
}

// Definisci ABSPATH per evitare exit
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

// Stub per funzioni WordPress
if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() { return true; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) { return true; }
}
if (!function_exists('wp_die')) {
    function wp_die($msg) { die($msg); }
}

try {
    // Includi solo le funzioni
    require_once BTR_PLUGIN_DIR . 'admin/ajax-functions.php';
    
    // Esegui test
    header('Content-Type: application/json');
    
    $git_commits = btr_get_recent_git_commits();
    $doc_changes = btr_get_recent_changes_from_docs();
    $all_changes = array_unique(array_merge($doc_changes, $git_commits));
    
    echo json_encode([
        'success' => true,
        'data' => [
            'changes' => array_slice($all_changes, 0, 15),
            'git_count' => count($git_commits),
            'doc_count' => count($doc_changes)
        ]
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}