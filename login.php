<?php
require_once 'config.php';
require_once __DIR__ . '/core/auditoria.php';
require_once __DIR__ . '/core/empresa_logo.php';

$logo_inicial = emp_logo_inicial($conexion);

if (isset($_SESSION['logueado']) && $_SESSION['logueado'] === true) {
  header("Location: " . url('index.php'));
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario = trim((string)($_POST['usuario'] ?? ''));
  $clave = (string)($_POST['clave'] ?? '');

  if ($usuario !== '' && $clave !== '') {
    $stmt = mysqli_prepare($conexion, "SELECT usuario_id, usuario, usuario_nombre, password, tabla_estado_registro_id FROM conf__usuarios WHERE usuario = ? LIMIT 1");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, "s", $usuario);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);

      if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        if ((int)$user['tabla_estado_registro_id'] === 1 && password_verify($clave, $user['password'])) {
          session_regenerate_id(true);
          // Limpiar residuos contextuales anteriores
          unset($_SESSION['empresa_id'], $_SESSION['modulo_id']);
          $_SESSION['usuario_id'] = (int)$user['usuario_id'];
          $_SESSION['usuario'] = $user['usuario'];
          $_SESSION['usuario_nombre'] = $user['usuario_nombre'];
          $_SESSION['logueado'] = true;
          auditoria_login($conexion, (int)$user['usuario_id'], $user['usuario_nombre'], true);
          session_write_close();
          header("Location: " . url('index.php'));
          exit;
        } else {
          auditoria_login($conexion, (int)$user['usuario_id'], $usuario, false, 'Contraseña incorrecta o usuario inhabilitado');
          $error = "Usuario o contraseña incorrectos";
        }
      } else {
        auditoria_login($conexion, 0, $usuario, false, 'Usuario no encontrado');
        $error = "Usuario o contraseña incorrectos";
      }
      mysqli_stmt_close($stmt);
    } else {
      $error = "Error interno del servidor. Por favor, reintente.";
    }
  } else {
    $error = "Por favor, complete todos los campos.";
  }
}
?>
<!DOCTYPE html>
<html lang="es" translate="no">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="google" content="notranslate" />
  <title>Login</title>

  <link rel="icon" type="image/png" href="<?= htmlspecialchars($logo_inicial) ?>">
  <link rel="shortcut icon" href="<?= htmlspecialchars($logo_inicial) ?>">

  <link rel="stylesheet" href="<?= asset_local('css/admin-lte-4.0.0-beta2/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/fontawesome-5.15.4/css/all.min.css') ?>" />

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

    /* Ocultamiento visual de contraseña sin activar el gestor de contraseñas del navegador */
    .input-clave-oculta {
      -webkit-text-security: disc !important;
      text-security: disc !important;
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
          <img src="<?= htmlspecialchars($logo_inicial) ?>" alt="Logo" class="brand-image brand-image-empresa shadow"
            style="max-height: 80px; width: auto;" />
        </div>
      </div>
      <div class="card-body">
        <p class="login-box-msg">Inicia sesión para comenzar</p>

        <form action="" method="post" autocomplete="off">

          <div class="input-group mb-3">
            <input type="text" name="usuario" class="form-control" placeholder="Usuario" autocomplete="off" spellcheck="false" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-user"></span>
              </div>
            </div>
          </div>

          <div class="input-group mb-3">
            <input type="text" id="inputClave" name="clave" class="form-control input-clave-oculta" placeholder="Contraseña" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" required>
            <div class="input-group-append">
              <button type="button" class="input-group-text" id="btnToggleClave" title="Mostrar/Ocultar contraseña" style="cursor: pointer; background-color: #e9ecef; border: 1px solid #ced4da;">
                <span class="fas fa-eye-slash" id="iconoClave"></span>
              </button>
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

  <script src="<?= asset_local('js/jquery-3.6.0/jquery.min.js') ?>"></script>
  <script src="<?= asset_local('js/bootstrap-5.3.3/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset_local('js/admin-lte-4.0.0-beta2/adminlte.min.js') ?>"></script>
  <script>
    document.getElementById('btnToggleClave')?.addEventListener('click', function () {
      const input = document.getElementById('inputClave');
      const icono = document.getElementById('iconoClave');
      if (input.classList.contains('input-clave-oculta')) {
        input.classList.remove('input-clave-oculta');
        icono.className = 'fas fa-eye text-primary';
      } else {
        input.classList.add('input-clave-oculta');
        icono.className = 'fas fa-eye-slash';
      }
    });
  </script>
</body>

</html>