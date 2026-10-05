<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("SELECT * FROM gestion__comprobantes WHERE comprobante_id = 166");
$comp = $res->fetch_assoc();
print_r($comp);

if ($comp['tabla_origen_id'] && $comp['registro_origen_id']) {
    $res2 = $conexion->query("SELECT * FROM conf__tablas WHERE tabla_id = {$comp['tabla_origen_id']}");
    $tab = $res2->fetch_assoc();
    echo "Tabla origen: {$tab['tabla']}\n";
    $res3 = $conexion->query("SELECT * FROM {$tab['tabla']} WHERE factura_id = {$comp['registro_origen_id']}");
    if ($res3) print_r($res3->fetch_assoc());
}
