<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("SHOW COLUMNS FROM gestion__comprobantes LIKE 'signo'");
print_r($res->fetch_assoc());
