<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$empresa_id = 2;
$tipo_origen_codigo = 'RCX';

$sql = "SELECT c.comprobante_id, ct.codigo AS comprobante_tipo, ct.comprobante_tipo,
               c.comprobante_pv, c.comprobante_nro, c.f_emision, c.f_vto,
               c.moneda_id, m.codigo AS moneda_codigo, m.simbolo AS moneda_simbolo,
               c.importe_total, (c.importe_pendiente * c.signo) AS importe_pendiente,
               c.signo,
               e.entidad_id, e.entidad_nombre,
               c.tabla_estado_registro_id,
               COALESCE(er.codigo_estandar, er_directo.codigo_estandar) AS estado_codigo
        FROM gestion__comprobantes c
        JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
        JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
        JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
        JOIN gestion__comprobantes_tipos_cancelaciones tc 
             ON tc.comprobante_tipo_destino_id = c.comprobante_tipo_id 
            AND tc.empresa_id = c.empresa_id
        JOIN gestion__comprobantes_tipos cto 
             ON cto.comprobante_tipo_id = tc.comprobante_tipo_origen_id
        LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = c.tabla_estado_registro_id
        LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
        LEFT JOIN conf__estados_registros er_directo ON er_directo.estado_registro_id = c.tabla_estado_registro_id
        WHERE c.empresa_id = ?
          AND cto.codigo = ?
          AND ABS(c.importe_pendiente * c.signo) > 0.001
          AND COALESCE(er.codigo_estandar, er_directo.codigo_estandar) = 'CONFIRMADO'
        ORDER BY c.f_emision ASC, c.comprobante_id ASC";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "is", $empresa_id, $tipo_origen_codigo);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
echo "Resultados con soporte híbrido de estado (tabla_estado o directo):\n";
while ($r = mysqli_fetch_assoc($res)) {
    echo sprintf("Comp ID: %4d | Cliente: %4d - %-25s | %-6s %04d-%08d | Total: %10.2f | Pend: %10.2f | Estado: %s\n",
        $r['comprobante_id'], $r['entidad_id'], substr($r['entidad_nombre'], 0, 25),
        $r['comprobante_tipo'], $r['comprobante_pv'], $r['comprobante_nro'],
        $r['importe_total'], $r['importe_pendiente'], $r['estado_codigo']
    );
}
