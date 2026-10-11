<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__productos_tipos WHERE empresa_id = 6 OR empresa_id = 0");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Tipo ID: {$r['producto_tipo_id']} | Emp: {$r['empresa_id']} | Nombre: {$r['producto_tipo']}\n";
}

$uRes = mysqli_query($conn, "SELECT * FROM gestion__unidades_medida");
while ($r = mysqli_fetch_assoc($uRes)) {
    echo "UM ID: {$r['unidad_medida_id']} | Simbolo: {$r['unidad_medida_simbolo']} | Nombre: {$r['unidad_medida_nombre']}\n";
}
