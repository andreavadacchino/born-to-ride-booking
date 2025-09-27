<?php
// Semplice test per verificare che il sito funzioni
require_once('wp-config.php');

echo "Sito WordPress funzionante!<br>";
echo "Versione PHP: " . phpversion() . "<br>";
echo "Plugin born-to-ride attivo: " . (is_plugin_active('born-to-ride-booking/born-to-ride-booking.php') ? 'Sì' : 'No') . "<br>";

// Testa la classe anagrafici
if (class_exists('BTR_Anagrafici_Shortcode')) {
    echo "Classe BTR_Anagrafici_Shortcode caricata correttamente!<br>";
    
    // Verifica che il metodo esista
    if (method_exists('BTR_Anagrafici_Shortcode', 'recalculate_preventivo_totals')) {
        echo "Metodo recalculate_preventivo_totals presente!<br>";
    } else {
        echo "ERRORE: Metodo recalculate_preventivo_totals non trovato!<br>";
    }
} else {
    echo "ERRORE: Classe BTR_Anagrafici_Shortcode non caricata!<br>";
}
?>