<?php
$host = 'localhost';
$user = 'u368960646_gestion';
$pass = 'Coco3948#';
$dbname = 'u368960646_gestion';

$conexion  = mysqli_connect($host, $user, $pass, $dbname);

if (!$conexion ) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Opcionalmente, configurar charset
mysqli_set_charset($conexion , "utf8mb4");
