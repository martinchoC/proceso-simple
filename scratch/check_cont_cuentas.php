<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db.php';

$res = $conn->query("SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE empresa_id = 2 AND es_imputable = 1 ORDER BY codigo");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['cont_cuenta_id']} | Cod: {$r['codigo']} | {$r['nombre']}\n";
}
