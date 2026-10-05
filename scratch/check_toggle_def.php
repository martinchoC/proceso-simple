<?php
$c = file_get_contents('templates/adminlte/js/adminlte.js');
$pos = strpos($c, 'SELECTOR_DATA_TOGGLE$1');
while ($pos !== false) {
    echo substr($c, max(0, $pos - 50), 120) . "\n---\n";
    $pos = strpos($c, 'SELECTOR_DATA_TOGGLE$1', $pos + 1);
}
