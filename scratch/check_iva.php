<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = 6 OR empresa_id = 0");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['iva_alicuota_id']} | Emp: {$r['empresa_id']} | Porc: {$r['porcentaje']} | Nombre: {$r['alicuota_nombre']}\n";
}
