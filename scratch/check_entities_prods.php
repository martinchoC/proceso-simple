<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT entidad_id, entidad_nombre, cuit, fecha_alta FROM gestion__entidades WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['entidad_id']} | {$r['entidad_nombre']} | CUIT: {$r['cuit']} | Creado: {$r['fecha_alta']}\n";
}

$pRes = mysqli_query($conn, "SELECT producto_id, producto_codigo, producto_nombre FROM gestion__productos WHERE empresa_id = 6");
echo "\n--- Productos empresa 6 ---\n";
while ($r = mysqli_fetch_assoc($pRes)) {
    echo "ID: {$r['producto_id']} | {$r['producto_codigo']} | {$r['producto_nombre']}\n";
}
