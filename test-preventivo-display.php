<?php
require_once('wp-load.php');

// Preventivo ID
$preventivo_id = 36728;

// Visualizza il preventivo usando lo shortcode
echo "<h2>Test Visualizzazione Preventivo #$preventivo_id</h2>";
echo do_shortcode('[btr_riepilogo_preventivo id="' . $preventivo_id . '"]');