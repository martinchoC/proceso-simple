<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT producto_id, empresa_id, producto_codigo, producto_nombre, aplica_compra, aplica_venta, es_servicio FROM gestion__productos WHERE (empresa_id = 6 OR empresa_id = 2) AND aplica_compra = 1 LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['producto_id']} | Emp: {$r['empresa_id']} | Cod: {$r['producto_codigo']} | Nom: {$r['producto_nombre']}\n";
}

$cRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM gestion__productos WHERE empresa_id = 6 AND aplica_compra = 1");
echo "Productos compra empresa 6: " . mysqli_fetch_assoc($cRes)['c'] . "\n";
