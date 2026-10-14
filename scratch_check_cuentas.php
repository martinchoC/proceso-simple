<?php
require_once 'c:/xampp/htdocs/casalucho/db.php';
$res = mysqli_query($conn, "DESCRIBE gestion__cont_cuentas");
echo "=== COLUMNAS gestion__cont_cuentas ===\n";
while ($r = mysqli_fetch_assoc($res)) {
    echo "{$r['Field']} ({$r['Type']})\n";
}

$res2 = mysqli_query($conn, "SELECT COUNT(*) as total FROM gestion__cont_cuentas WHERE empresa_id = 2");
$r2 = mysqli_fetch_assoc($res2);
echo "Total cuentas empresa 2: {$r2['total']}\n";

$res3 = mysqli_query($conn, "SELECT cont_cuenta_id, codigo, nombre, nivel FROM gestion__cont_cuentas WHERE empresa_id = 2 LIMIT 5");
while ($r = mysqli_fetch_assoc($res3)) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
