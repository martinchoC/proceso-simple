<?php
/**
 * @var int $status
 * @var string $mensaje
 */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error <?= (int) $status ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="error-body">
  <main class="error-caja">
    <p class="error-codigo"><?= (int) $status ?></p>
    <p><?= e($mensaje) ?></p>
    <?php if (!empty($error_id)): ?>
      <p class="error-id">Código del error: <strong><?= e($error_id) ?></strong></p>
      <p class="error-id">Buscá ese código en <code>storage/logs/app.log</code>.</p>
    <?php endif; ?>
    <a class="btn btn-primario" href="<?= e(url('/catalogo')) ?>">Volver al catálogo</a>
  </main>
</body>
</html>
