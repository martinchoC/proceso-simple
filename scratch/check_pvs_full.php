<?php
require 'db.php';
$res = $conn->query('
    SELECT pvc.punto_venta_id, pv.nombre AS pv_nombre, ct.codigo, ct.comprobante_tipo
    FROM gestion__puntos_venta_comprobantes pvc
    JOIN gestion__puntos_venta pv ON pv.punto_venta_id = pvc.punto_venta_id
    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id
    ORDER BY pvc.punto_venta_id, ct.codigo
');
$by_pv = [];
while($r = $res->fetch_assoc()) {
    $by_pv[$r['punto_venta_id'] . ' - ' . $r['pv_nombre']][] = $r['codigo'];
}
print_r($by_pv);
