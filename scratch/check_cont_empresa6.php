<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT cont_cuenta_id, cuenta_codigo, cuenta_nombre FROM gestion__cont_cuentas WHERE empresa_id = 6 AND (cuenta_nombre LIKE '%Deudor%' OR cuenta_nombre LIKE '%Cliente%' OR cuenta_codigo LIKE '1.1.2%')");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['cont_cuenta_id']} | Cod: {$r['cuenta_codigo']} | Nombre: {$r['cuenta_nombre']}\n";
}

$res2 = mysqli_query($conn, "SELECT cont_cuenta_id, cuenta_codigo, cuenta_nombre FROM gestion__cont_cuentas WHERE empresa_id = 6 AND (cuenta_nombre LIKE '%Venta%' OR cuenta_codigo LIKE '4%') LIMIT 10");
while ($r = mysqli_fetch_assoc($res2)) {
    echo "ID: {$r['cont_cuenta_id']} | Cod: {$r['cuenta_codigo']} | Nombre: {$r['cuenta_nombre']}\n";
}
