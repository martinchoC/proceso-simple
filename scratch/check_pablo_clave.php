<?php
require_once __DIR__ . '/../config.php';
$r = mysqli_query($conexion, "SELECT usuario_id, usuario, clave FROM conf__usuarios WHERE usuario = 'pablo'");
$row = mysqli_fetch_assoc($r);
print_r($row);
