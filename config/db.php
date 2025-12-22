<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'gestion_multipyme';

$conexion  = mysqli_connect($host, $user, $pass, $dbname);

if (!$conexion ) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Opcionalmente, configurar charset
mysqli_set_charset($conexion , "utf8mb4");
