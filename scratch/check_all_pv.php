<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__puntos_venta");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['punto_venta_id']} | Empresa: {$r['empresa_id']} | Codigo Fiscal: {$r['codigo_fiscal']} | Nombre: {$r['nombre']}\n";
}
