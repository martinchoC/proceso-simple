<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT DISTINCT c.tabla_estado_registro_id, count(*) as cant, ct.codigo, ct.comprobante_tipo
    FROM gestion__comprobantes c
    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
    GROUP BY c.tabla_estado_registro_id, ct.codigo, ct.comprobante_tipo
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
