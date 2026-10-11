<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT iva_alicuota_id, iva_alicuota_nombre, porcentaje, codigo_afip FROM gestion__impuestos__iva_alicuotas WHERE empresa_id IN (0, 6)");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        echo "{$row['iva_alicuota_id']} | {$row['iva_alicuota_nombre']} | {$row['porcentaje']} | AFIP: {$row['codigo_afip']}\n";
    }
}
$r2 = mysqli_query($conn, "SELECT iva_alicuota_id, iva_alicuota_nombre, porcentaje, codigo_afip FROM gestion__iva_alicuotas WHERE empresa_id IN (0, 6)");
if ($r2) {
    echo "--- gestion__iva_alicuotas ---\n";
    while ($row = mysqli_fetch_assoc($r2)) {
        echo "{$row['iva_alicuota_id']} | {$row['iva_alicuota_nombre']} | {$row['porcentaje']} | AFIP: {$row['codigo_afip']}\n";
    }
}
