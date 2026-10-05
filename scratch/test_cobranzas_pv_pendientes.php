<?php
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion/cobranzas_model.php';

$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$empresa_id = 2;

echo "=== 1. TEST PUNTOS DE VENTA ===\n";
$pvs = obtenerPuntosVentaCobranza($conexion, $empresa_id);
echo "Total Puntos de Venta encontrados: " . count($pvs) . "\n";
foreach ($pvs as $pv) {
    echo "  - PV ID: {$pv['punto_venta_id']} | Sucursal: {$pv['sucursal_id']} ({$pv['sucursal_nombre']}) | Nombre: {$pv['nombre']}\n";
}

echo "\n=== 2. TEST PENDIENTES CLIENTE 313 (ABEJER WALTER) ===\n";
$pendientes_313 = tesoreriaObtenerComprobantesCancelables($conexion, $empresa_id, 'RCX', 313);
echo "Comprobantes pendientes para cliente 313: " . count($pendientes_313) . "\n";
foreach ($pendientes_313 as $c) {
    echo "  - Comp #{$c['comprobante_id']}: {$c['comprobante_tipo']} " . str_pad($c['comprobante_pv'], 4, '0', STR_PAD_LEFT) . "-" . str_pad($c['comprobante_nro'], 8, '0', STR_PAD_LEFT) . " | Total: {$c['importe_total']} | Pendiente: {$c['importe_pendiente']} | Signo: {$c['signo']}\n";
}

echo "\n=== 3. TEST PENDIENTES CLIENTE 2820 (ACCESORIOS ARCHILLA) ===\n";
$pendientes_2820 = tesoreriaObtenerComprobantesCancelables($conexion, $empresa_id, 'RCX', 2820);
echo "Comprobantes pendientes para cliente 2820: " . count($pendientes_2820) . "\n";
foreach ($pendientes_2820 as $c) {
    echo "  - Comp #{$c['comprobante_id']}: {$c['comprobante_tipo']} " . str_pad($c['comprobante_pv'], 4, '0', STR_PAD_LEFT) . "-" . str_pad($c['comprobante_nro'], 8, '0', STR_PAD_LEFT) . " | Total: {$c['importe_total']} | Pendiente: {$c['importe_pendiente']} | Signo: {$c['signo']}\n";
}

echo "\n=== 4. TEST PENDIENTES CLIENTE 3943 (BAEZ PABLO ANDRES) ===\n";
$pendientes_3943 = tesoreriaObtenerComprobantesCancelables($conexion, $empresa_id, 'RCX', 3943);
echo "Comprobantes pendientes para cliente 3943: " . count($pendientes_3943) . "\n";
foreach ($pendientes_3943 as $c) {
    echo "  - Comp #{$c['comprobante_id']}: {$c['comprobante_tipo']} " . str_pad($c['comprobante_pv'], 4, '0', STR_PAD_LEFT) . "-" . str_pad($c['comprobante_nro'], 8, '0', STR_PAD_LEFT) . " | Total: {$c['importe_total']} | Pendiente: {$c['importe_pendiente']} | Signo: {$c['signo']}\n";
}
