<?php
<?php
// get_imagen.php
require_once __DIR__ . '/../../db.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$sql = "SELECT imagen_tipo, imagen_data FROM conf__imagenes WHERE imagen_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    header('Content-Type: ' . $row['imagen_tipo']);
    echo $row['imagen_data'];
} else {
    header('HTTP/1.0 404 Not Found');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>