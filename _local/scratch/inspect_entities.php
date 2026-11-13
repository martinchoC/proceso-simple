<?php
require_once __DIR__ . '/../../db.php';

echo "--- TABLES ML ---\n";
$res = mysqli_query($conexion, "SHOW TABLES LIKE 'ml__%'");
while ($r = mysqli_fetch_row($res)) {
    $t = $r[0];
    $count_res = mysqli_query($conexion, "SELECT COUNT(*) FROM `$t`");
    $cnt = $count_res ? mysqli_fetch_row($count_res)[0] : 0;
    echo "$t: $cnt registros\n";
}

echo "\n--- TIPOS DE ENTIDAD ---\n";
$res_et = mysqli_query($conexion, "SELECT * FROM ml__entidades_tipos");
while ($r = mysqli_fetch_assoc($res_et)) {
    echo "ID: {$r['entidad_tipo_id']} | Codigo: {$r['codigo']} | Nombre: {$r['nombre']}\n";
}

echo "\n--- EMPRESA 2 ---\n";
$res_emp = mysqli_query($conexion, "SELECT * FROM conf__empresas WHERE empresa_id = 2");
if ($r = mysqli_fetch_assoc($res_emp)) {
    print_r($r);
}
