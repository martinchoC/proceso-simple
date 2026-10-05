<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query('SELECT min(importe_pendiente), max(importe_pendiente), min(signo), max(signo) FROM gestion__comprobantes');
print_r($res->fetch_row());

$res2 = $conexion->query('SELECT * FROM gestion__comprobantes WHERE signo < 0 LIMIT 5');
while ($r = $res2->fetch_assoc()) {
    echo "ID: {$r['comprobante_id']}, tipo_id: {$r['comprobante_tipo_id']}, total: {$r['importe_total']}, pend: {$r['importe_pendiente']}, signo: {$r['signo']}\n";
}
