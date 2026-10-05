<?php
require_once __DIR__ . '/../db.php';

echo "=== SUCURSALES ===\n";
$res = $conn->query("SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "=== BOCAS ===\n";
$res = $conn->query("SELECT boca_id, empresa_id, sucursal_id, boca_nombre, codigo, es_deposito, es_principal FROM gestion__bocas");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "=== PUNTOS DE VENTA ===\n";
$res = $conn->query("SELECT punto_venta_id, empresa_id, sucursal_id, boca_id, nombre, codigo_fiscal FROM gestion__puntos_venta");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "=== CUENTAS TESORERIA ===\n";
$res = $conn->query("SELECT tesoreria_cuenta_id, empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, saldo_actual FROM gestion__tesoreria_cuentas");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        print_r($r);
    }
} else {
    echo "No hay o error en gestion__tesoreria_cuentas: " . $conn->error . "\n";
}
