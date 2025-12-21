<?php
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/plantilla.php';
$contenido = __FILE__;
include TEMPLATE_PATH . '/layout.php';
?>

<div class="card">
  <div class="card-body">
    <h3>¡Hola, <?= $_SESSION['nombre'] ?>!</h3>
    <p>Bienvenido al sistema. Empresa ID: <?= $_SESSION['empresa_id'] ?></p>
  </div>
</div>
