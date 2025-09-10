<?php
/**
 * Born to Ride Booking - Standalone CHANGELOG Reader
 * 
 * Versione standalone per test e debug del lettore CHANGELOG
 * NON includere nella distribuzione finale
 * 
 * @package Born_To_Ride_Booking
 */

// Imposta header JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Path del CHANGELOG
$changelog_path = dirname(dirname(__FILE__)) . '/CHANGELOG.md';

// Funzione per pulire il testo
function clean_text($text) {
    // Rimuovi markdown
    $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
    $text = str_replace('`', '', $text);
    
    // Rimuovi emoji comuni
    $text = preg_replace('/^[🔧🐛✨⚡🔒📚♻️🎨💥🚀🏗️📦🔥⬆️📋]+\s*/', '', $text);
    
    // Normalizza spazi
    return trim(preg_replace('/\s+/', ' ', $text));
}

// Leggi il CHANGELOG
if (!file_exists($changelog_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'CHANGELOG.md non trovato'
    ]);
    exit;
}

$content = file_get_contents($changelog_path);
$changes = [];

// Dividi in sezioni di versione
$sections = preg_split('/^## \[/m', $content);

// Analizza le prime 5 versioni
foreach (array_slice($sections, 1, 5) as $section) {
    // Estrai versione
    if (preg_match('/^([^\]]+)\]\s*-\s*(.+)$/m', $section, $version_match)) {
        $version = $version_match[1];
        $date = trim($version_match[2]);
        
        // Estrai modifiche
        if (preg_match_all('/^-\s+(.+)$/m', $section, $changes_matches)) {
            foreach ($changes_matches[1] as $change) {
                $clean = clean_text($change);
                if (!empty($clean) && strlen($clean) > 10) {
                    $changes[] = $clean;
                }
            }
        }
    }
    
    // Limita a 20 modifiche
    if (count($changes) >= 20) {
        break;
    }
}

// Output JSON
echo json_encode([
    'success' => true,
    'data' => [
        'changes' => $changes,
        'count' => count($changes),
        'source' => 'CHANGELOG.md (standalone)',
        'file' => $changelog_path
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);