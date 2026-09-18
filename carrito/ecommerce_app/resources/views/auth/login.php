<?php
/**
 * @var \App\Support\Csrf $csrf
 * @var array<string,string[]> $flash
 */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1e40af">
  <title>Ingresar — CASALUCHO Autoherrajes</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="login-body">
  <main class="login-caja">
    <div class="login-marca">
      <img src="<?= e(asset('img/logo.png')) ?>" alt="CASALUCHO Autoherrajes" class="login-logo">
      <span>CASALUCHO<br>Autoherrajes</span>
    </div>
    <p class="login-sub">Acceso exclusivo para clientes mayoristas</p>

    <?= $this->partial('layouts/flash', ['flash' => $flash ?? []]) ?>

    <form method="post" action="<?= e(url('/login')) ?>" autocomplete="on" novalidate>
      <?= $csrf->field() ?>

      <div class="campo">
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" class="control"
               maxlength="20" required autofocus autocomplete="username">
      </div>

      <div class="campo">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" class="control"
               maxlength="200" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primario btn-bloque">Ingresar</button>
    </form>

    <p class="login-ayuda">
      ¿No podés entrar? Escribinos y verificamos que tu usuario esté habilitado.
    </p>
  </main>
</body>
</html>
