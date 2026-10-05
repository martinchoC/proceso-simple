<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT pv.punto_venta_id, pv.empresa_id, pv.sucursal_id, pv.nombre, pv.codigo_fiscal, pv.tabla_estado_registro_id, s.sucursal_nombre
    FROM gestion__puntos_venta pv
    LEFT JOIN gestion__sucursales s ON s.sucursal_id = pv.sucursal_id
    WHERE pv.empresa_id = 2
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
