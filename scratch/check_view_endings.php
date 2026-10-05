<?php
$files = glob(__DIR__ . '/../modules/estudio_contable/*.php');
foreach ($files as $f) {
    if (strpos($f, '_ajax.php') !== false || strpos($f, '_model.php') !== false) continue;
    $lines = file($f);
    $last_lines = array_slice($lines, -8);
    echo "=== " . basename($f) . " ===\n";
    echo implode("", $last_lines) . "\n";
}
