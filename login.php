<?php

require_once 'config/db.php';
//require_once 'core/plantilla.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
    $clave = $_POST['clave'];
    
    $query = "SELECT * FROM conf__usuarios WHERE usuario = '$usuario' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($clave, $user['password'])) {
            $sid = bin2hex(random_bytes(8));
            $usuario_id = $user['usuario_id'];

            $insert = "INSERT INTO conf__usuarios_sesiones (sid, usuario_id) VALUES ('$sid', '$usuario_id')";
            mysqli_query($conn, $insert);

            header("Location: index.php?sid=$sid");
            exit;
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado";
    }
}
// Incluir la vista de login usando plantilla
//cargar_plantilla('app/views/login_view.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Login - Gestión Multipyme</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- CSS AdminLTE -->
  
  <link rel="stylesheet" href="templates/adminlte4/css/adminlte.min.css" />
</head>
<body class="hold-transition login-page">

<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>Gestión</b>Multipyme</a>
  </div>
  <!-- /.login-logo -->

  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Inicia sesión para comenzar</p>

      <form action="" method="post">
        <div class="input-group mb-3">
          <input type="text" name="usuario" class="form-control" placeholder="Usuario" value="<?php echo $_POST['usuario'];?>" required />
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user"></span></div>
          </div>
        </div>

       <div class="input-group mb-3">
          <input type="password" name="clave" class="form-control" placeholder="Contraseña" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
          </div>
        </div>
      </form>

      <?php if ($error): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </div>
    <!-- /.login-card-body -->
  </div>
</div>

<!-- JS AdminLTE -->
<script src="templates/adminlte4/assets/plugins/jquery/jquery.min.js"></script>
<script src="templates/adminlte4/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="templates/adminlte4/assets/dist/js/adminlte.min.js"></script>

</body>
</html>
