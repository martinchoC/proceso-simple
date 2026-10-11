<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE empresa_id = 6 AND (codigo LIKE '5%' OR nombre LIKE '%Gasto%' OR nombre LIKE '%Compra%')");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['cont_cuenta_id']} | Cod: {$r['codigo']} | Nombre: {$r['nombre']}\n";
}

$pRes = mysqli_query($conn, "SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE empresa_id = 6 AND (nombre LIKE '%Proveedor%' OR codigo LIKE '2.1%')");
echo "\n--- Proveedores ---\n";
while ($r = mysqli_fetch_assoc($pRes)) {
    echo "ID: {$r['cont_cuenta_id']} | Cod: {$r['codigo']} | Nombre: {$r['nombre']}\n";
}
