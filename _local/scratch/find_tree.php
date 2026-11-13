<?php
$lines = file(__DIR__ . '/../../templates/adminlte/js/adminlte.js');
foreach ($lines as $num => $line) {
    if (stripos($line, 'tree') !== false) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}
