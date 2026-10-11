<?php
require_once __DIR__ . '/../db.php';

echo "=== TIPOS DE COMPROBANTES DE COMPRA PARA EMPRESA 6 ===\n";
$r = mysqli_query($conn, "SELECT comprobante_tipo_id, empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_tipo, codigo, letra 
FROM gestion__comprobantes_tipos 
WHERE empresa_id = 6 
ORDER BY comprobante_tipo_id");
while ($row = mysqli_fetch_assoc($r)) {
    echo "{$row['comprobante_tipo_id']} | Grupo: {$row['comprobante_grupo_id']} | Subgrupo: {$row['comprobante_subgrupo_id']} | {$row['codigo']} | {$row['comprobante_tipo']} | Letra: {$row['letra']}\n";
}
exit;

echo "\n=== SUCURSALES Y BOCAS/DEPOSITOS PARA EMPRESA 6 ===\n";
$s = mysqli_query($conn, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = 6");
while ($row = mysqli_fetch_assoc($s)) {
    echo "Sucursal: {$row['sucursal_id']} - {$row['sucursal_nombre']}\n";
    $b = mysqli_query($conn, "SELECT boca_id, boca_nombre, es_deposito FROM gestion__bocas WHERE sucursal_id = {$row['sucursal_id']} AND empresa_id = 6");
    while ($brow = mysqli_fetch_assoc($b)) {
        echo "   Boca/Dep: {$brow['boca_id']} - {$brow['boca_nombre']} (es_dep: {$brow['es_deposito']})\n";
    }
}

echo "\n=== CONDICIONES DE PAGO PARA EMPRESA 6 ===\n";
$cp = mysqli_query($conn, "SELECT condicion_pago_id, condicion_pago, dias FROM gestion__condiciones_pago WHERE empresa_id IN (0, 6)");
while ($row = mysqli_fetch_assoc($cp)) {
    echo "{$row['condicion_pago_id']} - {$row['condicion_pago']} ({$row['dias']} dias)\n";
}

echo "\n=== ALICUOTAS IVA ===\n";
$iva = mysqli_query($conn, "SELECT iva_alicuota_id, iva_alicuota_nombre, porcentaje, codigo_afip FROM gestion__iva_alicuotas");
while ($row = mysqli_fetch_assoc($iva)) {
    echo "{$row['iva_alicuota_id']} - {$row['iva_alicuota_nombre']} ({$row['porcentaje']}%) | AFIP: {$row['codigo_afip']}\n";
}
