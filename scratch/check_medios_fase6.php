<?php
require_once __DIR__ . '/../db.php';

foreach (['gestion__pagos_impuestos_medios', 'gestion__rendiciones_reembolsos_medios'] as $t) {
    echo "\n=== TABLA: $t ===\n";
    $r = $conn->query("SHOW COLUMNS FROM $t");
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            echo "{$c['Field']} ({$c['Type']})\n";
        }
    }
}
