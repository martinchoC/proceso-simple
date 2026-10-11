<?php
require_once __DIR__ . '/../db.php';

$res = $conexion->query("
    SELECT condicion_pago_id, codigo, condicion_pago, dias, es_contado, es_cuenta_corriente
    FROM gestion__condiciones_pago
    WHERE empresa_id IN (0, 6) AND tabla_estado_registro_id = 1
");

while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
