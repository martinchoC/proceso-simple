<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT c.comprobante_id, c.empresa_id, c.comprobante_tipo_id, ct.codigo, ct.comprobante_tipo, c.importe_pendiente, c.signo
    FROM gestion__comprobantes c
    LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
    WHERE c.comprobante_id IN (1, 2, 3, 4, 7, 8, 9, 10, 118, 120, 126)
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
