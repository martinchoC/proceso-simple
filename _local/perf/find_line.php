<?php
$lines = file(__DIR__ . '/../../modules/gestion/clientes.php');
foreach ($lines as $i => $line) {
    if (stripos($line, 'mail(') !== false) {
        echo "Línea " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
