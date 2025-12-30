<?php
require_once '../../../config/db.php';

$pagina_id = intval($_POST['pagina_id']);
$nombre = trim($_POST['nombre']);
$descripcion = trim($_POST['descripcion']);

if ($pagina_id > 0 && $nombre !== '') {
    $stmt = mysqli_prepare($conn, "INSERT INTO conf__paginas_funciones (pagina_id, nombre, descripcion) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iss", $pagina_id, $nombre, $descripcion);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo "<p style='color:green;'>Función '$nombre' creada con éxito.</p>";
    } else {
        echo "<p style='color:red;'>No se pudo crear la función.</p>";
    }

    mysqli_stmt_close($stmt);
} else {
    echo "<p style='color:red;'>Datos inválidos.</p>";
}
