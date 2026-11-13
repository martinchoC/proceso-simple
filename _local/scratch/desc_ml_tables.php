<?php
require_once __DIR__ . '/../../db.php';

function descTable($conn, $tbl) {
    echo "=== $tbl ===\n";
    $r = mysqli_query($conn, "DESCRIBE `$tbl`");
    while ($c = mysqli_fetch_assoc($r)) {
        echo "  {$c['Field']} ({$c['Type']})\n";
    }
}

descTable($conexion, 'ml__entidades');
descTable($conexion, 'ml__entidades_ubicaciones');
descTable($conexion, 'ml__entidades_empresas');
descTable($conexion, 'ml__obligaciones');
descTable($conexion, 'ml__ciclos');
descTable($conexion, 'ml__ciclos_tareas');
