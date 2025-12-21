<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);

    if (!empty($nombre)) {
        $sql = "INSERT INTO conf__empresas (nombre, estado) VALUES ('$nombre', 1)";
        if (mysqli_query($conn, $sql)) {
            $msg = "Empresa creada correctamente.";
        } else {
            $error = "Error al crear empresa: " . mysqli_error($conn);
        }
    } else {
        $error = "El nombre no puede estar vacío.";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Registrar Empresa</title></head>
<body>
<h2>Registrar Empresa</h2>
<form method="POST">
    <label>Nombre de la empresa:</label><br>
    <input type="text" name="nombre" required><br><br>
    <button type="submit">Crear empresa</button>
</form>
<?php
if (isset($msg)) echo "<p style='color:green;'>$msg</p>";
if (isset($error)) echo "<p style='color:red;'>$error</p>";
?>
</body>
</html>