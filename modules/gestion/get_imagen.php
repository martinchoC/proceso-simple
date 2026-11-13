<?php
// get_imagen.php - Sirve imágenes desde la base de datos con caché HTTP, ETag y miniaturas
$imagen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($imagen_id <= 0) {
    header('HTTP/1.0 404 Not Found');
    echo 'Imagen no encontrada';
    exit;
}

$is_thumb = isset($_GET['thumb']) && $_GET['thumb'] == 1;
$thumb_size = isset($_GET['size']) ? min(300, max(40, intval($_GET['size']))) : 100;

$cache_dir = __DIR__ . '/../../assets/cache/imagenes';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0775, true);
}

$cache_file = $is_thumb ? ($cache_dir . "/thumb_{$imagen_id}_{$thumb_size}.webp") : null;

// 1. Si es miniatura y ya existe en caché de disco, servir directamente sin tocar MySQL
if ($cache_file && file_exists($cache_file)) {
    $file_mtime = filemtime($cache_file);
    $file_size = filesize($cache_file);
    $etag = '"' . md5("disk-{$imagen_id}-{$file_mtime}-{$file_size}") . '"';

    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=604800, immutable');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    header('Content-Type: image/webp');
    header('Content-Length: ' . $file_size);
    readfile($cache_file);
    exit;
}

// 2. Conectar a la base de datos
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// 3. Consultar primero metadatos ligeros (sin descargar el BLOB pesado)
$sql_meta = "SELECT imagen_tipo, imagen_tamanio, imagen_creacion FROM conf__imagenes WHERE imagen_id = ? AND tabla_estado_registro_id = 1";
$stmt_meta = mysqli_prepare($conexion, $sql_meta);

if (!$stmt_meta) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error en la consulta';
    exit;
}

mysqli_stmt_bind_param($stmt_meta, "i", $imagen_id);
mysqli_stmt_execute($stmt_meta);
mysqli_stmt_bind_result($stmt_meta, $imagen_tipo, $imagen_tamanio, $imagen_creacion);

if (!mysqli_stmt_fetch($stmt_meta)) {
    mysqli_stmt_close($stmt_meta);
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
        <rect width="200" height="200" fill="#f8f9fa"/>
        <text x="100" y="100" font-family="Arial" font-size="14" fill="#6c757d" text-anchor="middle">Sin imagen</text>
    </svg>';
    exit;
}
mysqli_stmt_close($stmt_meta);

// 4. Calcular ETag para validación condicional HTTP 304
$etag_base = "{$imagen_id}-{$imagen_tamanio}-{$imagen_creacion}" . ($is_thumb ? "-thumb-{$thumb_size}" : "");
$etag = '"' . md5($etag_base) . '"';

header('ETag: ' . $etag);
header('Cache-Control: public, max-age=86400');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// 5. Solo si el cliente no tenía la imagen en caché, leer el BLOB de MySQL
$sql_data = "SELECT imagen_data FROM conf__imagenes WHERE imagen_id = ?";
$stmt_data = mysqli_prepare($conexion, $sql_data);

if (!$stmt_data) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error al recuperar imagen';
    exit;
}

mysqli_stmt_bind_param($stmt_data, "i", $imagen_id);
mysqli_stmt_execute($stmt_data);
mysqli_stmt_bind_result($stmt_data, $imagen_data);
mysqli_stmt_fetch($stmt_data);
mysqli_stmt_close($stmt_data);

if (!$imagen_data) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// 6. Si se solicitó miniatura y GD está disponible, generar y guardar en disco
if ($is_thumb && extension_loaded('gd') && function_exists('imagecreatefromstring')) {
    $img = @imagecreatefromstring($imagen_data);
    if ($img !== false) {
        $orig_w = imagesx($img);
        $orig_h = imagesy($img);

        // Crop cuadrado centrado
        if ($orig_w > $orig_h) {
            $src_w = $orig_h;
            $src_h = $orig_h;
            $src_x = (int)(($orig_w - $orig_h) / 2);
            $src_y = 0;
        } else {
            $src_w = $orig_w;
            $src_h = $orig_w;
            $src_x = 0;
            $src_y = (int)(($orig_h - $orig_w) / 2);
        }

        $thumb = imagecreatetruecolor($thumb_size, $thumb_size);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $img, 0, 0, $src_x, $src_y, $thumb_size, $thumb_size, $src_w, $src_h);
        imagedestroy($img);

        $saved = false;
        if (function_exists('imagewebp')) {
            $saved = @imagewebp($thumb, $cache_file, 80);
        } elseif (function_exists('imagejpeg')) {
            $cache_file = $cache_dir . "/thumb_{$imagen_id}_{$thumb_size}.jpg";
            $saved = @imagejpeg($thumb, $cache_file, 80);
        }
        imagedestroy($thumb);

        if ($saved && file_exists($cache_file)) {
            $file_size = filesize($cache_file);
            header('Content-Type: ' . (str_ends_with($cache_file, '.webp') ? 'image/webp' : 'image/jpeg'));
            header('Content-Length: ' . $file_size);
            readfile($cache_file);
            exit;
        }
    }
}

// 7. Servir imagen original completa si no fue miniatura o si GD falló como fallback
$content_type = $imagen_tipo ? $imagen_tipo : 'image/jpeg';
header('Content-Type: ' . $content_type);
header('Content-Length: ' . strlen($imagen_data));
echo $imagen_data;
?>