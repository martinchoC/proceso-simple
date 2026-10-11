<?php
// get_imagen.php - Sirve imágenes desde la base de datos
require_once __DIR__ . '/../../db.php';

$imagen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($imagen_id <= 0) {
    header('HTTP/1.0 404 Not Found');
    echo 'Imagen no encontrada';
    exit;
}

$conexion = $conn;

$sql = "SELECT imagen_data, imagen_tipo FROM conf__imagenes WHERE imagen_id = ? AND tabla_estado_registro_id = 1";
$stmt = mysqli_prepare($conexion, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $imagen_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $imagen_data, $imagen_tipo);
    
    if (mysqli_stmt_fetch($stmt) && $imagen_data) {
        // Establecer el header correcto según el tipo de imagen
        if ($imagen_tipo) {
            header('Content-Type: ' . $imagen_tipo);
        } else {
            header('Content-Type: image/jpeg');
        }
        
        // Cache por 1 hora
        header('Cache-Control: public, max-age=3600');
        
        // Enviar la imagen
        echo $imagen_data;
    } else {
        // Imagen no encontrada - devolver un placeholder
        header('HTTP/1.0 404 Not Found');
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
            <rect width="200" height="200" fill="#f8f9fa"/>
            <text x="100" y="100" font-family="Arial" font-size="14" fill="#6c757d" text-anchor="middle">Sin imagen</text>
        </svg>';
    }
    
    mysqli_stmt_close($stmt);
} else {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error en la consulta';
}

mysqli_close($conexion);
?>