<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__productos_categorias WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['producto_categoria_id']} | Nombre: {$r['producto_categoria']}\n";
}

$res2 = mysqli_query($conn, "SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE empresa_id = 6 AND (nombre LIKE '%Venta%' OR codigo LIKE '4%')");
while ($r = mysqli_fetch_assoc($res2)) {
    echo "ID: {$r['cont_cuenta_id']} | Cod: {$r['codigo']} | Nombre: {$r['nombre']}\n";
}
