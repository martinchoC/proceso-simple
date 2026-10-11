<?php
require_once __DIR__ . '/../db.php';

echo "=== FACTURAS REALES CARGADAS PARA EMPRESA 6 (Developsam S.A.S.) ===\n";
$sql = "SELECT vf.venta_factura_id, pv.codigo_fiscal as pv_fiscal, vf.comprobante_nro, vf.CAE, vf.f_vto_CAE,
               vf.f_emision, vf.importe_total, e.entidad_nombre, e.cuit,
               c.importe_total as comp_total, c.importe_pendiente
        FROM gestion__ventas_facturas vf 
        JOIN gestion__entidades e ON vf.entidad_id = e.entidad_id 
        LEFT JOIN gestion__puntos_venta pv ON vf.punto_venta_id = pv.punto_venta_id
        LEFT JOIN gestion__comprobantes c ON vf.comprobante_id = c.comprobante_id 
        WHERE vf.empresa_id = 6 
        ORDER BY vf.comprobante_nro ASC";

$r = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($r)) {
    printf("Factura B %05d-%08d | Emisión: %s | Total: $%10.2f | Pendiente: $%10.2f | CAE: %s (Vto %s) | CUIT: %s | %s\n",
        $row['pv_fiscal'],
        $row['comprobante_nro'],
        $row['f_emision'],
        $row['importe_total'],
        $row['importe_pendiente'],
        $row['CAE'],
        $row['f_vto_CAE'],
        $row['cuit'],
        $row['entidad_nombre']
    );
}

echo "\n=== ESTADO DE PAGOS / COBRANZAS ===\n";
$cob = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM gestion__cobranzas WHERE empresa_id = 6");
$cobRow = mysqli_fetch_assoc($cob);
echo "Cobranzas registradas: " . $cobRow['cnt'] . " (100% impago, saldo pendiente = total)\n";

echo "\n=== ASIENTOS CONTABLES GENERADOS ===\n";
$as = mysqli_query($conn, "SELECT ca.*
FROM gestion__cont_asientos ca
WHERE ca.empresa_id = 6
ORDER BY ca.cont_asiento_id ASC LIMIT 1");
$row = mysqli_fetch_assoc($as);
print_r($row);
    $ad = mysqli_query($conn, "SELECT * FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {$row['cont_asiento_id']} LIMIT 1");
    $d = mysqli_fetch_assoc($ad);
    print_r($d);
