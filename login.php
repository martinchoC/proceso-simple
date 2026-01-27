<?php
require_once 'config.php';

if (isset($_SESSION['logueado']) && $_SESSION['logueado'] === true) {
  header("Location: " . url('index.php'));
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario = mysqli_real_escape_string($conexion, $_POST['usuario']);
  $clave = $_POST['clave'];

  $query = "SELECT * FROM conf__usuarios WHERE usuario = '$usuario' LIMIT 1";
  $result = mysqli_query($conexion, $query);

  if ($result && mysqli_num_rows($result) === 1) {
    $user = mysqli_fetch_assoc($result);
    if (password_verify($clave, $user['password'])) {
      session_regenerate_id(true);
      $_SESSION['usuario_id'] = $user['usuario_id'];
      $_SESSION['usuario'] = $user['usuario'];
      $_SESSION['usuario_nombre'] = $user['usuario_nombre'];
      $_SESSION['logueado'] = true;
      session_write_close();
      header("Location: " . url('index.php'));
      exit;
    } else {
      $error = "Contraseña incorrecta";
    }
  } else {
    $error = "Usuario no encontrado";
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>

  <link rel="stylesheet" href="<?= asset_local('css/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/all.min.css') ?>" />

  <style>
    /* --- PARCHE DE ESTILO PARA INPUTS --- */
    /* Esto fuerza a que el grupo del input se comporte como una caja flexible perfecta */
    .input-group {
      display: flex;
      flex-wrap: nowrap;
      /* Evita que el icono baje a otra línea */
      align-items: stretch;
      /* Estira ambos elementos a la misma altura */
      width: 100%;
    }

    /* Aseguramos que el input ocupe el espacio disponible */
    .input-group .form-control {
      position: relative;
      flex: 1 1 auto;
      width: 1%;
      min-width: 0;
      height: calc(2.25rem + 2px);
      /* Altura estándar de Bootstrap */
    }

    /* Ajuste para el contenedor del icono */
    .input-group-append {
      display: flex;
      /* Crucial para que el hijo se estire */
      margin-left: -1px;
    }

    /* Ajuste para la caja gris del icono */
    .input-group-text {
      display: flex;
      align-items: center;
      padding: 0.375rem 0.75rem;
      margin-bottom: 0;
      font-size: 1rem;
      font-weight: 400;
      line-height: 1.5;
      color: #495057;
      text-align: center;
      white-space: nowrap;
      background-color: #e9ecef;
      border: 1px solid #ced4da;
      border-radius: 0 0.25rem 0.25rem 0;
      /* Bordes redondeados solo a la derecha */
    }
  </style>
</head>

<body class="hold-transition login-page">
  <div class="login-box">
    <div class="card card-outline card-primary">
      <div class="card-header text-center">
        <h1 style="white-space: nowrap; font-size: 2rem; display: block; text-decoration: none; color: inherit;">
          <b>Gestión</b>Multipyme
        </h1>
        <div class="mt-2">
          <img src="<?= asset('img/developsam_logo.png') ?>" alt="Logo" class="brand-image opacity-75 shadow"
            style="max-height: 80px; width: auto;" />
        </div>
      </div>
      <div class="card-body">
        <p class="login-box-msg">Inicia sesión para comenzar</p>

        <form action="" method="post">

          <div class="input-group mb-3">
            <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-user"></span>
              </div>
            </div>
          </div>

          <div class="input-group mb-3">
            <input type="password" name="clave" class="form-control" placeholder="Contraseña" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
            </div>
          </div>
        </form>

        <?php if ($error): ?>
          <div class="alert alert-danger mt-3 text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <script src="<?= asset_local('js/jquery.min.js') ?>"></script>
  <script src="<?= asset_local('js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset_local('js/adminlte.min.js') ?>"></script>
</body>

</html>