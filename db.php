<?php
$envPath = __DIR__ . "/.env";

if (!file_exists($envPath)) {
    die("Error: El archivo .env no existe en el servidor. Asegúrate de haberlo subido.");
}

require_once $envPath;

// Verificar que las constantes existan antes de usarlas
if (!defined('IP_HOST') || !defined('DB_USER')) {
    die("Error: Las constantes de conexión no están definidas en el archivo .env. Asegúrate de que el archivo empiece con &lt;?php y use define().");
}

$host = IP_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");