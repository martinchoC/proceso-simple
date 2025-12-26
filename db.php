<?php
require_once ".env";

$host = IP_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

$conexion  = mysqli_connect($host, $user, $pass, $dbname);

if (!$conexion ) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Opcionalmente, configurar charset
mysqli_set_charset($conexion , "utf8mb4");
