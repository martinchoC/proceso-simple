<?php
// El date.timezone del servidor (php.ini) está en Europe/Berlin, no en horario
// argentino. Sin esto, date('Y-m-d') queda varias horas adelantado respecto a
// Argentina, y validaciones como "la fecha desde no puede ser menor a hoy"
// (ver gestion/clientes_model.php::agregarCondicionCliente) rechazan guardados
// legítimos durante la tarde/noche porque el servidor ya "cree" que es el día
// siguiente.
date_default_timezone_set('America/Argentina/Buenos_Aires');

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
$conexion = $conn;