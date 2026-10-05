<?php
require_once __DIR__ . '/../modules/gestion/tesoreria_core_model.php';
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');

// Simular llamada de cobranzas_ajax para entidad 313
$entidad_id = 313;
$empresa_id = 2;

$sql = "SELECT c.comprobante_id, ct.codigo AS comprobante_tipo, ct.comprobante_tipo,
               c.comprobante_pv, c.comprobante_nro, c.f_emision, c.f_vto,
               c.moneda_id, m.codigo AS moneda_codigo, m.simbolo AS moneda_simbolo,
               c.importe_total, (c.importe_pendiente * c.signo) AS importe_pendiente,
               c.signo,
               e.entidad_id, e.entidad_nombre
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
          AND (er.codigo_estandar = 'CONFIRMADO' OR er_directo.codigo_estandar = 'CONFIRMADO')
          AND c.entidad_id = ?
        ORDER BY c.f_emision ASC, c.comprobante_id ASC";

$stmt = mysqli_prepare($conexion, $sql);
$tipo = 'RCX';
mysqli_stmt_bind_param($stmt, "isi", $empresa_id, $tipo, $entidad_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$list = [];
while ($r = mysqli_fetch_assoc($res)) {
    $list[] = $r;
}
echo "Comprobantes pendientes encontrados para cliente 313: " . count($list) . "\n";
print_r($list);
