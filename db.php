<?php
require_once __DIR__ . "/.env";

$host = IP_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Opcionalmente, configurar charset
mysqli_set_charset($conn, "utf8mb4");
