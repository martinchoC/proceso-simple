<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/facturas_proveedores_model.php';

echo "=== VERIFICACION DE FACTURAS DE PROVEEDORES (EMPRESA 6) ===\n";
$fps = obtenerFacturasProveedor($conn, 6, 557);
printf("%-4s | %-16s | %-10s | %-35s | %12s | %12s | %-12s\n", "ID", "COMPROBANTE", "FECHA", "PROVEEDOR", "TOTAL", "PENDIENTE", "ESTADO");
echo str_repeat("-", 105) . "\n";
foreach ($fps as $f) {
    // Buscar importe_pendiente en comprobantes
    $cRes = mysqli_query($conn, "SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = {$f['comprobante_id']}");
    $cRow = mysqli_fetch_assoc($cRes);
    $pend = $cRow ? $cRow['importe_pendiente'] : $f['total'];
    
    $comp_str = sprintf("FA %04d-%08d", $f['comprobante_pv'], $f['comprobante_nro']);
    printf("%-4d | %-16s | %-10s | %-35s | $%11.2f | $%11.2f | %-12s\n",
        $f['factura_proveedor_id'],
        $comp_str,
        $f['f_emision'],
        substr($f['entidad_nombre'], 0, 35),
        $f['total'],
        $pend,
        $f['estado_info']['estado_registro'] ?? 'Confirmado'
    );
}

echo "\n=== BALANCE CONTABLE DE ASIENTOS DE COMPRA ===\n";
$as = mysqli_query($conn, "SELECT ca.cont_asiento_id, ca.descripcion, 
    SUM(CASE WHEN cad.tipo = 'A' THEN cad.importe_local ELSE 0 END) as total_debe,
    SUM(CASE WHEN cad.tipo = 'P' THEN cad.importe_local ELSE 0 END) as total_haber
FROM gestion__cont_asientos ca
JOIN gestion__cont_asientos_detalles cad ON ca.cont_asiento_id = cad.cont_asiento_id
WHERE ca.empresa_id = 6 AND ca.cont_tipo_asiento_id = 9
GROUP BY ca.cont_asiento_id
ORDER BY ca.cont_asiento_id ASC");

$diferencia_total = 0;
while ($row = mysqli_fetch_assoc($as)) {
    $diff = abs(floatval($row['total_debe']) - floatval($row['total_haber']));
    $diferencia_total += $diff;
    printf("Asiento #%d | Debe: $%11.2f | Haber: $%11.2f | Dif: $%0.2f | %s\n",
        $row['cont_asiento_id'],
        $row['total_debe'],
        $row['total_haber'],
        $diff,
        substr($row['descripcion'], 0, 45)
    );
}
echo "Diferencia total en libro diario: $" . number_format($diferencia_total, 2) . " (Balance perfecto: $0.00)\n";
