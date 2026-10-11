<?php
require_once __DIR__ . '/../db.php';

echo "=== MEDIOS DE PAGO ===\n";
$q_mp = $conexion->query("SELECT * FROM gestion__medios_pago WHERE tabla_estado_registro_id = 1");
while ($r = $q_mp->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== TESORERIA CUENTAS EMPRESA 6 ===\n";
$q_tc = $conexion->query("SELECT tc.*, cc.codigo, cc.nombre as cuenta_contable_nombre 
    FROM gestion__tesoreria_cuentas tc 
    LEFT JOIN gestion__cont_cuentas cc ON tc.cont_cuenta_id = cc.cont_cuenta_id
    WHERE tc.empresa_id = 6");
while ($r = $q_tc->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== PUNTOS DE VENTA EMPRESA 6 ===\n";
$q_pv = $conexion->query("SELECT * FROM gestion__puntos_venta WHERE empresa_id = 6");
while ($r = $q_pv->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
