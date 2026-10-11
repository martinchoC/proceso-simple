<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/facturas_proveedores_model.php';

echo "=== TEST PREVISUALIZACION Y CARGA ARCA ===\n";

$empresa_id = 6;
$test_items = [
    [
        'fecha' => '05/10/2026',
        'tipo' => '1 - Factura A',
        'pv' => 1,
        'nro' => 99991,
        'cuit' => '30639453738',
        'razon_social' => 'TELECOM ARGENTINA S.A.',
        'cae' => '74123456789012',
        'neto' => 50000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 10500.00,
        'otros_tributos' => 0.00,
        'total' => 60500.00
    ]
];

$preview = fpPrevisualizarImportacionArca($conn, $empresa_id, $test_items);
echo "Preview resultado:\n";
print_r($preview);

$opciones = [
    'sucursal_id' => 4,
    'deposito_id' => 0,
    'condicion_pago_id' => 11,
    'crear_proveedores_auto' => 1
];

$res = fpImportarComprobantesArca($conn, $empresa_id, $preview['items'], $opciones, 1);
echo "\nImportacion resultado:\n";
print_r($res);

// Comprobar factura creada
$chk = mysqli_query($conn, "SELECT fp.*, e.entidad_nombre, c.comprobante_id 
FROM gestion__facturas_proveedores fp 
JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
LEFT JOIN gestion__comprobantes c ON fp.comprobante_id = c.comprobante_id
WHERE fp.empresa_id = $empresa_id AND fp.comprobante_nro = 99991");
$row = mysqli_fetch_assoc($chk);
echo "\nFactura creada en BD:\n";
print_r($row);

// Limpiar la factura de prueba y proveedor de prueba creado
if ($row) {
    mysqli_query($conn, "DELETE FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id = {$row['factura_proveedor_id']}");
    mysqli_query($conn, "DELETE FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {$row['factura_proveedor_id']}");
    if ($row['comprobante_id']) {
        mysqli_query($conn, "DELETE FROM gestion__comprobantes WHERE comprobante_id = {$row['comprobante_id']}");
    }
    mysqli_query($conn, "DELETE FROM gestion__entidades_condiciones_proveedores WHERE entidad_id = {$row['entidad_id']}");
    mysqli_query($conn, "DELETE FROM gestion__entidades_sucursales WHERE entidad_id = {$row['entidad_id']}");
    mysqli_query($conn, "DELETE FROM gestion__entidades WHERE entidad_id = {$row['entidad_id']}");
    echo "\nFactura y proveedor de prueba eliminados correctamente tras validar.\n";
}
