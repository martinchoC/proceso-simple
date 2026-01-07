<?php
$servidor = "localhost";
$usuario = "root";
$clave = "";
$base = "gestion_multipyme";

$conexion = mysqli_connect($servidor, $usuario, $clave, $base);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
mysqli_set_charset($conexion, "utf8mb4");
?>