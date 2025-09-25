<?php
/**
 * Build script per creare un pacchetto pulito del plugin per produzione
 * 
 * Questo script crea una versione pulita del plugin escludendo:
 * - File di sviluppo e test
 * - Backup file
 * - Documentazione non essenziale
 * - File di configurazione IDE
 * - Cache e log
 * 
 * @package BornToRideBooking
 * @version 1.0.252
 */

if (php_sapi_name() !== 'cli') {
    echo "Questo script deve essere eseguito da riga di comando\n";
    exit(1);
}

$plugin_dir = __DIR__;
$build_dir = $plugin_dir . '/build';
$plugin_name = 'born-to-ride-booking';
$version = '1.0.252';

echo "=== Build Plugin $plugin_name v$version ===\n";

// Pulisci build directory
if (file_exists($build_dir)) {
    echo "Pulizia directory build...\n";
    shell_exec("rm -rf " . escapeshellarg($build_dir));
}

mkdir($build_dir, 0755, true);

// File e directory da includere (solo essenziali)
$essential_files = [
    // File principale
    'born-to-ride-booking.php',
    
    // Directory includes (solo classi essenziali)
    'includes/',
    
    // Directory admin (solo file necessari)
    'admin/',
    
    // Templates
    'templates/',
    
    // Assets
    'assets/',
    
    // Librerie esterne
    'lib/',
    
    // File di configurazione
    'updater-config.json',
    '.distignore',
    
    // README essenziali
    'README.md',
];

// File e directory da escludere
$exclude_patterns = [
    // File di sviluppo
    '*/class-btr-developer-menu.php',
    '*/build-release-ajax.php',
    '*/build-archive.php',
    '*/ajax-test-direct.php',
    '*/test-ajax.php',
    '*/ajax-functions.php',
    
    // File di test
    'test*',
    '*/test*',
    '*test.php',
    '*-test.php',
    'debug*.php',
    'fix-*.php',
    
    // Backup
    '*.bak',
    '*.backup',
    '*.old',
    '*.orig',
    
    // Documentazione non essenziale
    'MODIFICHE-DETTAGLIATE-*.md',
    'RIPRISTINO-*.md',
    'PUNTO-RIPRISTINO-*.md',
    'DOCUMENTAZIONE-MODIFICHE-*.md',
    'TODO.md',
    'CHANGELOG-DEV.md',
    
    // Configurazione IDE
    '.idea/',
    '.vscode/',
    '*.sublime-*',
    
    // Git
    '.git/',
    '.gitignore',
    '.gitattributes',
    
    // Cache e log
    '*.log',
    'debug.log',
    'error_log',
    
    // OS
    '.DS_Store',
    'Thumbs.db',
    'desktop.ini',
    
    // Editor
    '*.swp',
    '*.swo',
    '*~',
    
    // Node.js
    'node_modules/',
    'package.json',
    'package-lock.json',
    
    // Composer
    'vendor/',
    'composer.json',
    'composer.lock',
    
    // PHPUnit
    'phpunit.xml*',
    
    // Build script
    'build-plugin-zip.php',
    '*.sh',
    
    // File specifici da escludere
    'includes/class-btr-hotfix-loader.php',
    'admin/class-btr-diagnostic-ajax.php',
    'admin/class-btr-diagnostic-menu.php',
    'admin/payment-setup-helper.php',
];

echo "Copia file essenziali...\n";

foreach ($essential_files as $item) {
    $source = $plugin_dir . '/' . $item;
    
    if (!file_exists($source)) {
        echo "ATTENZIONE: $source non trovato, saltato\n";
        continue;
    }
    
    $dest = $build_dir . '/' . $item;
    
    if (is_dir($source)) {
        // Copia directory con rsync escludendo pattern
        $exclude_args = '';
        foreach ($exclude_patterns as $pattern) {
            $exclude_args .= ' --exclude=' . escapeshellarg($pattern);
        }
        
        $cmd = "rsync -av$exclude_args " . escapeshellarg($source . '/') . " " . escapeshellarg($dest . '/');
        echo "Esecuzione: $cmd\n";
        shell_exec($cmd);
    } else {
        // Copia file singolo
        $dest_dir = dirname($dest);
        if (!file_exists($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        copy($source, $dest);
        echo "Copiato: $item\n";
    }
}

echo "Rimozione file non essenziali...\n";

// Rimuovi file specifici che non vogliamo in produzione
$files_to_remove = [
    'includes/class-btr-hotfix-loader.php',
    'admin/class-btr-diagnostic-ajax.php', 
    'admin/class-btr-diagnostic-menu.php',
    'admin/payment-setup-helper.php',
    'admin/build-release-ajax.php',
    'admin/build-archive.php',
    'admin/ajax-test-direct.php',
    'admin/test-ajax.php',
    'admin/ajax-functions.php',
];

foreach ($files_to_remove as $file) {
    $full_path = $build_dir . '/' . $file;
    if (file_exists($full_path)) {
        unlink($full_path);
        echo "Rimosso: $file\n";
    }
}

echo "Verifica build...\n";

// Verifica che il file principale esista
if (!file_exists($build_dir . '/born-to-ride-booking.php')) {
    echo "ERRORE: File principale del plugin non trovato!\n";
    exit(1);
}

// Verifica che la directory includes esista
if (!file_exists($build_dir . '/includes')) {
    echo "ERRORE: Directory includes non trovata!\n";
    exit(1);
}

echo "Creazione pacchetto ZIP...\n";

$zip_filename = $plugin_dir . "/{$plugin_name}.zip";
$zip_cmd = "cd " . escapeshellarg($build_dir) . " && zip -r " . escapeshellarg($zip_filename) . " .";
shell_exec($zip_cmd);

if (!file_exists($zip_filename)) {
    echo "ERRORE: Creazione ZIP fallita!\n";
    exit(1);
}

$zip_size = filesize($zip_filename);
echo "Build completato con successo!\n";
echo "Pacchetto: $zip_filename\n";
echo "Dimensione: " . number_format($zip_size / 1024 / 1024, 2) . " MB\n";

// Pulizia
echo "Pulizia directory build temporanea...\n";
shell_exec("rm -rf " . escapeshellarg($build_dir));

echo "=== DONE ===\n";
