<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT tesoreria_cuenta_id, tesoreria_cuenta, saldo_actual FROM gestion__tesoreria_cuentas WHERE empresa_id = 2");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['tesoreria_cuenta_id']} | {$r['tesoreria_cuenta']} | Saldo: {$r['saldo_actual']}\n";
}
