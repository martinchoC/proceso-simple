<?php
// get_imagen.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// IMPORTANTE: Incluir db.php y obtener la conexión
require_once __DIR__ . '/../../db.php';
$conexion = $conn; // ← ESTA LÍNEA ES CRÍTICA

if (!$conexion) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    exit('Error de conexión a la base de datos');
}

$imagen_id = intval($_GET['id'] ?? 0);

if ($imagen_id == 0) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain');
    exit('ID de imagen no proporcionado');
}

// Usar consulta preparada para seguridad
$sql = "SELECT imagen_tipo, imagen_data FROM conf__imagenes WHERE imagen_id = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    exit('Error preparando consulta: ' . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $imagen_id);
mysqli_stmt_execute($stmt);

// Obtener resultados
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) == 0) {
    mysqli_stmt_close($stmt);
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain');
    exit('Imagen no encontrada (ID: ' . $imagen_id . ')');
}

// Vincular resultado
mysqli_stmt_bind_result($stmt, $imagen_tipo, $imagen_data);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (empty($imagen_data)) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain');
    exit('Imagen sin datos');
}

// Enviar headers correctos
header('Content-Type: ' . $imagen_tipo);
header('Content-Length: ' . strlen($imagen_data));
header('Cache-Control: max-age=86400, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Pragma: cache');

// Limpiar buffer de salida
if (ob_get_level()) {
    ob_end_clean();
}

// Enviar imagen
echo $imagen_data;
exit;
?>