<?php
require_once __DIR__ . '/../db.php';

$res = $conexion->query("SELECT condicion_pago_id, empresa_id, codigo, condicion_pago, tipo, dias_primer_vencimiento FROM gestion__condiciones_pago WHERE empresa_id IN (0, 6)");
while ($r = $res->fetch_assoc()) echo json_encode($r) . "\n";
