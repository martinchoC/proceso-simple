<?php
require_once __DIR__ . '/../../db.php';

function printColumns($table) {
    global $conn;
    echo "=== COLUMNS OF $table ===\n";
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    while ($r = mysqli_fetch_assoc($res)) {
        echo "  {$r['Field']} - {$r['Type']}\n";
    }
}

function printData($table, $where = "1 LIMIT 10") {
    global $conn;
    echo "=== DATA OF $table ($where) ===\n";
    $res = mysqli_query($conn, "SELECT * FROM `$table` WHERE $where");
    while ($r = mysqli_fetch_assoc($res)) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

printColumns('gestion__entidades');
printData('gestion__entidades', 'empresa_id = 6');

printColumns('gestion__ventas_facturas');
printData('gestion__ventas_facturas', 'empresa_id = 6 LIMIT 3');

printColumns('gestion__ventas_facturas_detalles');
