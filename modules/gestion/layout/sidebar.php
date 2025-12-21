<?php
require_once __DIR__ . '/../../../core/auth.php';

// Se asume que $usuario está ya validado antes de incluir este archivo
$modulo_id = 2; // stock, por ejemplo
$menu = obtener_menu_para_usuario($usuario['usuario_id'], $usuario['empresa_id'], $modulo_id);
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="#" class="brand-link">
    <span class="brand-text font-weight-light">Stock</span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column">
        <?php foreach ($menu as $item): ?>
          <li class="nav-item">
            <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
              <i class="nav-icon fas fa-file-alt"></i>
              <p><?= htmlspecialchars($item['nombre']) ?></p>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</aside>
</aside>
