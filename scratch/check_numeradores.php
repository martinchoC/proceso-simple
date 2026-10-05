<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT cn.*, pv.nombre as pv_nombre, ct.codigo as comp_tipo_codigo
    FROM gestion__comprobantes_numeradores cn
    LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = cn.punto_venta_id
    LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cn.comprobante_tipo_id
    WHERE cn.empresa_id = 2
");
while ($r = $res->fetch_assoc()) {
    echo "Num ID: {$r['numerador_id']} | PV: {$r['punto_venta_id']} ({$r['pv_nombre']}) | Tipo: {$r['comp_tipo_codigo']} ({$r['comprobante_tipo_id']}) | Ult Nro: {$r['ultimo_numero']}\n";
}
