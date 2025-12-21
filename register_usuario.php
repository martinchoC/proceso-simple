<?php
require_once 'config/db.php';

// Obtener listado de empresas para asignar
$empresas = [];
$res = mysqli_query($conn, "SELECT id, nombre FROM conf__empresas WHERE estado = 1");
while ($row = mysqli_fetch_assoc($res)) {
    $empresas[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_nombre = mysqli_real_escape_string($conn, $_POST['usuario_nombre']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $empresa_id = intval($_POST['empresa_id']);
    $duracion_sid_minutos = intval($_POST['duracion_sid_minutos']);

    if ($usuario_nombre && $email && $password && $empresa_id) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO conf__usuarios (usuario_nombre, email, password, duracion_sid_minutos) 
                VALUES ('$usuario_nombre', '$email', '$hash', $duracion_sid_minutos)";
        if (mysqli_query($conn, $sql)) {
            $usuario_id = mysqli_insert_id($conn);
            // Aquí podrías crear relaciones usuario-empresa si las manejas en otra tabla.
            $msg = "Usuario creado correctamente.";
        } else {
            $error = "Error al crear usuario: " . mysqli_error($conn);
        }
    } else {
        $error = "Completar todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Registrar Usuario</title></head>
<body>
<h2>Registrar Usuario</h2>
<form method="POST">
    <label>Nombre de usuario:</label><br>
    <input type="text" name="usuario_nombre" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Contraseña:</label><br>
    <input type="password" name="password" required><br><br>

    <label>Empresa:</label><br>
    <select name="empresa_id" required>
        <option value="">--Seleccione empresa--</option>
        <?php foreach ($empresas as $empresa): ?>
            <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nombre']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Duración sesión (minutos):</label><br>
    <input type="number" name="duracion_sid_minutos" value="60" min="1" max="1440"><br><br>

    <button type="submit">Crear usuario</button>
</form>

<?php
if (isset($msg)) echo "<p style='color:green;'>$msg</p>";
if (isset($error)) echo "<p style='color:red;'>$error</p>";
?>
</body>
</html>
