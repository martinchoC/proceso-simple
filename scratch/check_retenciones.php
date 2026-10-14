<?php
require 'db.php';
$res = $conexion->query("SELECT cont_cuenta_id, empresa_id, codigo, nombre FROM gestion__cont_cuentas WHERE codigo LIKE '2.1%' AND (nombre LIKE '%retenc%' OR nombre LIKE '%depositar%' OR nombre LIKE '%fiscal%') AND empresa_id = 2");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
