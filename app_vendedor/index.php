<?php
/**
 * Aplicación del vendedor (doc 28, pasos 9-7 / 9-8): web para el celular con la sesión del ERP. Elegir cliente de la
 * cartera → catálogo con su lista y condiciones → pedido en preparación (Borrador real, origen APP) → confirmar.
 * Solo con usa_app_vendedor y un usuario ligado a un vendedor activo; si no, no se puede usar. Sin modo sin conexión
 * (etapa 5): avisa si no hay conexión y no pierde lo cargado en pantalla. Backend: api.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/auditoria.php';
require_once __DIR__ . '/../core/pedidos_venta/app_vendedor.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$destino = 'index.php' . (isset($_GET['empresa_id']) && ctype_digit((string) $_GET['empresa_id']) ? '?empresa_id=' . (int) $_GET['empresa_id'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'salir') {
    if (csrf_valido($_POST['csrf_token'] ?? null)) {
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            auditoria_logout($conexion, $uid, (string) ($_SESSION['usuario_nombre'] ?? 'Usuario'));
        }
        $_SESSION = [];
        session_destroy();
    }
    header('Location: ' . $destino);
    exit;
}

$error = '';
$usuario_valor = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? 'ingresar') === 'ingresar') {
    $usuario_valor = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');
    if (!csrf_valido($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión de la página venció. Volvé a intentar.';
    } elseif ($usuario_valor === '' || $clave === '') {
        $error = 'Completá usuario y contraseña.';
    } else {
        $stmt = mysqli_prepare($conexion, "SELECT usuario_id, usuario, usuario_nombre, password, tabla_estado_registro_id FROM conf__usuarios WHERE usuario = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $usuario_valor);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($user && (int) $user['tabla_estado_registro_id'] === 1 && password_verify($clave, $user['password'])) {
                session_regenerate_id(true);
                unset($_SESSION['empresa_id'], $_SESSION['modulo_id']);
                $_SESSION['usuario_id'] = (int) $user['usuario_id'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['usuario_nombre'] = $user['usuario_nombre'];
                $_SESSION['logueado'] = true;
                auditoria_login($conexion, (int) $user['usuario_id'], $user['usuario_nombre'], true);
                session_write_close();
                header('Location: ' . $destino);
                exit;
            }
            auditoria_login($conexion, (int) ($user['usuario_id'] ?? 0), $usuario_valor, false, $user ? 'Contraseña incorrecta o usuario inhabilitado' : 'Usuario no encontrado');
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
$logueado = !empty($_SESSION['logueado']) && $_SESSION['logueado'] === true && $usuario_id > 0;
$empresas = $logueado ? pven_app_empresas($conexion, $usuario_id) : [];
$pedida = (int) ($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
$ids = array_column($empresas, 'empresa_id');
$empresa_id = in_array($pedida, $ids, true) ? $pedida : (int) ($ids[0] ?? 0);
$csrf = csrf_token();
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1f4e79" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#142536" media="(prefers-color-scheme: dark)">
  <title>Pedidos · Vendedor</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="icono.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="icono.svg">
  <link rel="stylesheet" href="<?= $e(asset_local('css/app-vendedor.css')) ?>">
</head>
<body<?= (!$logueado || !$empresas) ? ' class="av-pantalla-login"' : '' ?>>
<?php if (!$logueado): ?>
  <main class="av-centro"><div class="av-tarjeta">
    <img src="icono.svg" class="av-logo" alt="" width="64" height="64">
    <h1>Pedidos · Vendedor</h1>
    <p class="av-fila-sub">Ingresá con tu usuario del sistema.</p>
    <?php if ($error !== ''): ?>
      <div class="av-aviso av-aviso-error av-login-aviso"><?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="av-login">
      <input type="hidden" name="accion" value="ingresar">
      <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
      <label class="av-campo">Usuario
        <input type="text" name="usuario" class="av-input" value="<?= $e($usuario_valor) ?>" autocomplete="username" autocapitalize="none" required autofocus>
      </label>
      <label class="av-campo">Contraseña
        <input type="password" name="clave" class="av-input" autocomplete="current-password" required>
      </label>
      <button type="submit" class="av-btn av-btn-primario av-btn-ancho">Ingresar</button>
    </form>
  </div></main>
<?php elseif (!$empresas): ?>
  <main class="av-centro"><div class="av-tarjeta">
    <img src="icono.svg" class="av-logo" alt="" width="64" height="64">
    <h1>Pedidos</h1>
    <p class="av-fila-sub">Tu usuario no tiene habilitada la aplicación del vendedor en ninguna empresa.</p>
    <div class="av-login-acciones">
      <a class="av-btn" href="<?= $e(url('index.php')) ?>">Ir al sistema</a>
      <form method="post">
        <input type="hidden" name="accion" value="salir">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <button type="submit" class="av-btn">Cambiar de usuario</button>
      </form>
    </div>
  </div></main>
<?php else: ?>
  <div id="av" data-api="api.php" data-empresa="<?= $empresa_id ?>" data-csrf="<?= $e($csrf) ?>">
    <header class="av-barra">
      <button type="button" class="av-volver" data-av-volver hidden aria-label="Volver">&larr;</button>
      <div class="av-titulo" data-av-titulo>Clientes</div>
      <?php if (count($empresas) > 1): ?>
        <select class="av-empresa" data-av-empresa aria-label="Empresa">
          <?php foreach ($empresas as $em): ?>
            <option value="<?= (int) $em['empresa_id'] ?>" <?= (int) $em['empresa_id'] === $empresa_id ? 'selected' : '' ?>><?= $e($em['empresa']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <form method="post" class="av-salir-form">
        <input type="hidden" name="accion" value="salir">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <button type="submit" class="av-salir" title="Cerrar sesión">Salir</button>
      </form>
    </header>
    <div class="av-aviso" data-av-aviso hidden></div>
    <main data-av-vista></main>
  </div>
  <script src="<?= $e(asset_local('js/app-vendedor.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
