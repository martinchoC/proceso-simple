<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/facturas_proveedores_model.php';

// First update factura 86 total so it matches neto + IVA + otros_impuestos
$total_real = 1512000.00 + 317520.00 + 21233.98;
echo "Total real: $total_real\n";
mysqli_query($conn, "UPDATE gestion__facturas_proveedores SET total = $total_real WHERE factura_proveedor_id = 86");
mysqli_query($conn, "UPDATE gestion__comprobantes SET importe_total = $total_real WHERE comprobante_id = 290");

$res = generarAsientoContableFactura($conn, 86, 2, false);
echo "Resultado generarAsientoContableFactura:\n";
print_r($res);

$q = mysqli_query($conn, 'SELECT * FROM gestion__cont_asientos WHERE comprobante_id = 290');
$asiento = mysqli_fetch_assoc($q);
echo "Asiento en BD:\n";
print_r($asiento);
if ($asiento) {
    $q_det = mysqli_query($conn, "SELECT d.*, c.codigo, c.nombre 
                                  FROM gestion__cont_asientos_detalle d 
                                  LEFT JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cont_cuenta_id 
                                  WHERE d.cont_asiento_id = {$asiento['cont_asiento_id']}");
    echo "Detalles:\n";
    while ($r = mysqli_fetch_assoc($q_det)) {
        echo "Cuenta: {$r['cont_cuenta_id']} ({$r['codigo']} - {$r['nombre']}) | Importe: {$r['importe']} | Desc: {$r['descripcion']}\n";
    }
}
