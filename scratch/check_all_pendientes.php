<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT c.comprobante_id, c.empresa_id, c.entidad_id, e.entidad_nombre, ct.comprobante_tipo_id, ct.codigo as comp_tipo_codigo, ct.comprobante_tipo,
           c.comprobante_pv, c.comprobante_nro, c.importe_total, c.importe_pendiente, c.signo,
           ter.tabla_estado_registro_id, er.codigo_estandar
    FROM gestion__comprobantes c
    LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
    LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
    LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = c.tabla_estado_registro_id
    LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
    WHERE c.importe_pendiente > 0
    ORDER BY c.comprobante_id DESC
    LIMIT 30
");
while ($r = $res->fetch_assoc()) {
    echo sprintf("ID: %4d | Emp: %d | Entidad: %3d (%-20s) | Tipo: %-6s (ID %2d) | Nro: %04d-%08d | Pend: %10.2f | Signo: %2d | Estado: %s (%d)\n",
        $r['comprobante_id'], $r['empresa_id'], $r['entidad_id'], substr($r['entidad_nombre'] ?? '', 0, 20),
        $r['comp_tipo_codigo'], $r['comprobante_tipo_id'], $r['comprobante_pv'], $r['comprobante_nro'],
        $r['importe_pendiente'], $r['signo'], $r['codigo_estandar'], $r['tabla_estado_registro_id']
    );
}
