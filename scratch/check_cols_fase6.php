<?php
require_once __DIR__ . '/../db.php';

$tablas = [
    'gestion__obligaciones_impositivas',
    'gestion__pagos_impuestos',
    'gestion__rendiciones',
    'gestion__rendiciones_anticipos',
    'gestion__rendiciones_items',
    'gestion__rendiciones_reembolsos'
];

foreach ($tablas as $t) {
    echo "\n=== TABLA: $t ===\n";
    $r = $conn->query("SHOW COLUMNS FROM $t");
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            echo "{$c['Field']} ({$c['Type']})\n";
        }
    } else {
        echo "No existe o error: " . $conn->error . "\n";
    }
}
