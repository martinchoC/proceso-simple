<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT c.comprobante_id, c.empresa_id, c.comprobante_tipo_id, ct.codigo, c.importe_pendiente, c.signo,
           ter.tabla_estado_registro_id, er.codigo_estandar, c.entidad_id
    FROM gestion__comprobantes c
    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
    JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = c.tabla_estado_registro_id
    JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
    WHERE ct.codigo LIKE 'V_%'
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
