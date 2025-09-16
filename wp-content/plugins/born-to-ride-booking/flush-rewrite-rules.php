<?php
/**
 * Script per fare flush delle rewrite rules
 * Eseguilo nel browser: http://localhost:10018/wp-content/plugins/born-to-ride-booking/flush-rewrite-rules.php
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Aggiungi le rewrite rules
$rewrite = new BTR_Payment_Rewrite();
$rewrite->add_rewrite_rules();

// Flush rewrite rules
flush_rewrite_rules();

echo "✅ Rewrite rules aggiornate con successo!<br><br>";
echo "🔗 Ora puoi testare il link:<br>";
echo "<a href=\"http://localhost:10018/pagamento-gruppo/dbb66adcf0a490dc12c0c5740b3a8acefa0dac5875d517c37bcbaa086a0cfe30\">";
echo "http://localhost:10018/pagamento-gruppo/dbb66adcf0a490dc12c0c5740b3a8acefa0dac5875d517c37bcbaa086a0cfe30</a>";
