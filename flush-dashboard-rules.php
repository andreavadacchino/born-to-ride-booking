<?php
/**
 * Flush rewrite rules per dashboard pagamenti gruppo
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Forza registrazione endpoint
add_action('init', function() {
    add_rewrite_endpoint('group-payments', EP_ROOT | EP_PAGES);
});

// Flush rules
flush_rewrite_rules();

echo "✅ Rewrite rules aggiornate!<br><br>";
echo "Ora i link 'Gestisci' dovrebbero funzionare correttamente.<br>";
echo "<a href=\"http://localhost:10018/mio-account/group-payments/\">Torna alla dashboard pagamenti gruppo</a>";
