<?php
require_once __DIR__ . '/../db.php';

echo "=== PUNTOS DE VENTA (empresa 6) ===\n";
$res = mysqli_query($conn, "SELECT * FROM gestion__puntos_venta WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}

echo "=== TIPOS COMPROBANTES (Factura B) ===\n";
$res = mysqli_query($conn, "SELECT * FROM gestion__comprobantes_tipos WHERE (empresa_id = 6 OR empresa_id = 0) AND (comprobante_tipo LIKE '%Factura B%' OR letra = 'B')");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['comprobante_tipo_id']} | Empresa: {$r['empresa_id']} | Cod Fiscal: {$r['comprobante_fiscal_id']} | Nombre: {$r['comprobante_tipo']} | Letra: {$r['letra']}\n";
}

echo "=== SUCURSALES (empresa 6) ===\n";
$res = mysqli_query($conn, "SELECT * FROM gestion__sucursales WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}

echo "=== CONDICIONES DE PAGO (empresa 6) ===\n";
$res = mysqli_query($conn, "SELECT * FROM gestion__condiciones_pago WHERE empresa_id = 6 OR empresa_id = 0");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['condicion_pago_id']} | Empresa: {$r['empresa_id']} | Nombre: {$r['condicion_pago']}\n";
}

echo "=== MONEDAS (empresa 6) ===\n";
$res = mysqli_query($conn, "SELECT * FROM gestion__monedas WHERE empresa_id = 6 OR empresa_id = 0");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}

echo "=== ESTADOS REGISTROS ===\n";
$res = mysqli_query($conn, "SELECT * FROM conf__estados_registros WHERE tabla_id IN (SELECT tabla_id FROM conf__tablas WHERE tabla IN ('gestion__ventas_facturas', 'gestion__comprobantes'))");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Estado ID: {$r['tabla_estado_registro_id']} | Tabla ID: {$r['tabla_id']} | Estado: {$r['estado_registro']}\n";
}
