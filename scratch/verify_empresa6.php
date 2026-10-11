<?php
require_once __DIR__ . '/../db.php';

echo "=== CLIENTES EMPRESA 6 ===\n";
$res = mysqli_query($conn, "SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['entidad_id']} | CUIT: {$r['cuit']} | {$r['entidad_nombre']}\n";
}

echo "\n=== FACTURAS DE VENTA EMPRESA 6 ===\n";
$res = mysqli_query($conn, "SELECT vf.venta_factura_id, vf.punto_venta_id, vf.comprobante_nro, vf.f_emision, vf.importe_total, vf.CAE, vf.f_vto_CAE, e.entidad_nombre, c.importe_pendiente
FROM gestion__ventas_facturas vf
JOIN gestion__entidades e ON vf.entidad_id = e.entidad_id
LEFT JOIN gestion__comprobantes c ON vf.comprobante_id = c.comprobante_id
WHERE vf.empresa_id = 6
ORDER BY vf.comprobante_nro ASC");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Factura #{$r['comprobante_nro']} | PV: {$r['comprobante_pv']} | Emisión: {$r['f_emision']} | Total: \${$r['importe_total']} | Saldo Pendiente: \${$r['importe_pendiente']} | CAE: {$r['CAE']} (Vto: {$r['f_vto_CAE']}) | Cliente: {$r['entidad_nombre']}\n";
}

echo "\n=== ASIENTOS CONTABLES EMPRESA 6 ===\n";
$res = mysqli_query($conn, "SELECT ca.cont_asiento_id, ca.numero, ca.fecha, ca.concepto, ca.total_debe, ca.total_haber 
FROM gestion__cont_asientos ca 
WHERE ca.empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Asiento #{$r['numero']} | Fecha: {$r['fecha']} | Concepto: {$r['concepto']} | Debe: \${$r['total_debe']} | Haber: \${$r['total_haber']}\n";
}
