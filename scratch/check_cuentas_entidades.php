<?php
require __DIR__ . '/../db.php';

echo "=== CUENTAS DE CLIENTES (empresa 2) ===\n";
$res = $conn->query("SELECT cont_cuenta_id_cliente, count(*) as cant FROM gestion__entidades WHERE empresa_id = 2 AND es_cliente = 1 GROUP BY cont_cuenta_id_cliente");
while ($r = $res->fetch_assoc()) {
    $cId = $r['cont_cuenta_id_cliente'];
    $nombre = 'NULL';
    if ($cId) {
        $cRes = $conn->query("SELECT codigo, nombre FROM gestion__cont_cuentas WHERE cont_cuenta_id = $cId");
        if ($cRow = $cRes->fetch_assoc()) $nombre = "{$cRow['codigo']} - {$cRow['nombre']}";
    }
    echo "ID: $cId ($nombre) -> {$r['cant']} entidades\n";
}

echo "\n=== CUENTAS DE PROVEEDORES (empresa 2) ===\n";
$res = $conn->query("SELECT cont_cuenta_id_proveedor, count(*) as cant FROM gestion__entidades WHERE empresa_id = 2 AND es_proveedor = 1 GROUP BY cont_cuenta_id_proveedor");
while ($r = $res->fetch_assoc()) {
    $cId = $r['cont_cuenta_id_proveedor'];
    $nombre = 'NULL';
    if ($cId) {
        $cRes = $conn->query("SELECT codigo, nombre FROM gestion__cont_cuentas WHERE cont_cuenta_id = $cId");
        if ($cRow = $cRes->fetch_assoc()) $nombre = "{$cRow['codigo']} - {$cRow['nombre']}";
    }
    echo "ID: $cId ($nombre) -> {$r['cant']} entidades\n";
}

echo "\n=== CONCEPTOS EN gestion__empresas_cuentas_conceptos (empresa 2) ===\n";
$res = $conn->query("SELECT ecc.*, cc.codigo, cc.nombre FROM gestion__empresas_cuentas_conceptos ecc LEFT JOIN gestion__cont_cuentas cc ON ecc.cont_cuenta_id = cc.cont_cuenta_id WHERE ecc.empresa_id = 2");
while ($r = $res->fetch_assoc()) {
    echo "{$r['concepto']} -> ID {$r['cont_cuenta_id']} ({$r['codigo']} - {$r['nombre']})\n";
}
