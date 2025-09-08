<?php
/**
 * Funzioni per recuperare le modifiche suggerite
 */

// Funzione per recuperare i commit Git recenti
function btr_get_recent_git_commits($limit = 20) {
    $plugin_dir = BTR_PLUGIN_DIR;
    $commits = [];
    
    // Trova la directory .git anche se è in una directory superiore
    $git_dir = $plugin_dir;
    $found_git = false;
    
    // Cerca .git fino a 5 livelli sopra
    for ($i = 0; $i < 5; $i++) {
        if (is_dir($git_dir . '/.git')) {
            $found_git = true;
            break;
        }
        $git_dir = dirname($git_dir);
    }
    
    if ($found_git) {
        // Cambia alla directory git e ottieni il percorso relativo del plugin
        $relative_path = 'wp-content/plugins/born-to-ride-booking';
        
        // Esegui git log per ottenere i commit recenti del plugin
        $command = sprintf(
            'cd %s && git log --oneline --no-merges -n %d --pretty=format:"%%s" -- %s 2>/dev/null',
            escapeshellarg($git_dir),
            $limit,
            escapeshellarg($relative_path)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                // Pulisci e formatta il messaggio
                $message = trim($line);
                
                // Rimuovi prefissi comuni dai commit
                $message = preg_replace('/^(MILESTONE|CRITICAL FIX|URGENT FIX|FIX):\s*/i', '', $message);
                
                // Salta i commit di merge
                if (!empty($message) && stripos($message, 'Merge') !== 0) {
                    $commits[] = $message;
                }
            }
        }
    }
    
    return $commits;
}

// Funzione per recuperare le modifiche recenti dai file di documentazione
function btr_get_recent_changes_from_docs() {
    $changes = [];
    $plugin_dir = BTR_PLUGIN_DIR;
    
    // Controlla i file di documentazione modifiche recenti
    $doc_files = [
        'DOCUMENTAZIONE-MODIFICHE-2025-01-13.md',
        'DOCUMENTAZIONE-MODIFICHE-2025-01-11.md',
        'MODIFICHE-DETTAGLIATE-2025-01-10.md',
        'CHANGELOG.md'
    ];
    
    foreach ($doc_files as $doc_file) {
        $file_path = $plugin_dir . $doc_file;
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            
            // Per CHANGELOG.md, prendi solo le modifiche della versione più recente
            if ($doc_file === 'CHANGELOG.md') {
                // Estrai solo la sezione della versione più recente (1.0.30)
                if (preg_match('/## \[1\.0\.30\].*?(?=## \[|$)/s', $content, $section_match)) {
                    $content = $section_match[0];
                }
            }
            
            // Estrai le modifiche principali
            if (preg_match_all('/^[-*]\s+(.+)$/m', $content, $matches)) {
                foreach ($matches[1] as $change) {
                    // Pulisci e aggiungi solo modifiche rilevanti
                    $change = strip_tags($change);
                    // Rimuovi i percorsi dei file dalle modifiche
                    $change = preg_replace('/\s*\(.*?\)$/', '', $change);
                    // Rimuovi i numeri di riga
                    $change = preg_replace('/\s*\(riga[^)]+\)/', '', $change);
                    if (strlen($change) > 10 && strlen($change) < 200 && !strpos($change, '**')) {
                        $changes[] = $change;
                    }
                }
            }
        }
    }
    
    // Aggiungi anche le modifiche hardcoded basate sul lavoro recente
    $recent_work = [
        "Sistema notti extra dinamico completo - Recupero automatico dal backend",
        "Fix normalizzazione date Y-m-d per notti extra",
        "Supporto date multiple separate da virgole per notti extra",
        "Formattazione intelligente date multiple: 21, 22, 23/01/2026",
        "Caricamento dati mancanti nel metodo AJAX get_rooms()",
        "Frontend mostra numero dinamico invece di hardcoded '2 Notti extra'",
        "Validazione adulto obbligatorio - Ogni camera deve avere almeno un adulto",
        "Indicatore visivo rosso per bambini non selezionabili senza adulto",
        "Reset automatico assegnazioni bambini al cambio parametri",
        "Fix calcolo prezzi prima fase - Supplemento base per 2 notti",
        "Implementato date picker moderno con calendario italiano",
        "Aggiunto select province con ricerca e navigazione tastiera",
        "Fix duplicazione box info neonati al cambio partecipanti",
        "Fix visualizzazione info culla in tutti i form adulti",
        "Sistema etichette dinamiche per partecipanti e fasce bambini"
    ];
    
    // Unisci e rimuovi duplicati
    $all_changes = array_unique(array_merge($changes, $recent_work));
    
    return array_slice($all_changes, 0, 10);
}