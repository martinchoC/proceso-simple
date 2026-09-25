<?php
// templates/adminlte/header1.php

require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
  header("Location: " . url('login.php'));
  exit;
}

$ruta = $ruta_assets ?? '';

$nombre_usuario_avatar = $_SESSION['usuario_nombre'] ?? 'Usuario';

$palabras = explode(' ', $nombre_usuario_avatar);
$iniciales = '';
if (count($palabras) >= 1) {
  $iniciales .= strtoupper(substr($palabras[0], 0, 1));
}
if (count($palabras) >= 2) {
  $iniciales .= strtoupper(substr($palabras[1], 0, 1));
}
if (strlen($iniciales) < 2 && strlen($nombre_usuario_avatar) > 1 && count($palabras) == 1) {
  $iniciales = strtoupper(substr($nombre_usuario_avatar, 0, 2));
}

$colores_avatar = [
  '#007bff', // Azul
  '#6610f2', // Indigo
  '#6f42c1', // Purpura
  '#e83e8c', // Rosa
  '#dc3545', // Rojo
  '#fd7e14', // Naranja
  '#28a745', // Verde
  '#20c997', // Verde azulado
  '#17a2b8', // Cian
  '#343a40', // Gris oscuro
];

$indice_color = hexdec(substr(md5($nombre_usuario_avatar), 0, 5)) % count($colores_avatar);
$color_fondo_avatar = $colores_avatar[$indice_color];
?>

<!doctype html>
<html lang="es" translate="no">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Developsam | Multigestion</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="color-scheme" content="light dark" />
  <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
  <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
  <meta name="title" content="Developsam | Multigestion" />
  <meta name="author" content="Developsam" />
  <meta name="description" content="Developsam" />
  <meta name="google" content="notranslate" />

  <meta name="supported-color-schemes" content="light dark" />
  <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">
  <link rel="shortcut icon" href="<?= asset('img/logo.png') ?>">
  <link href="<?= asset_local('css/dataTables.bootstrap5.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/all.min.css') ?>" />
  <link href="<?= asset_local('css/sweetalert2-bootstrap-4.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/overlayscrollbars.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/bootstrap-icons.css') ?>">
  <link href="<?= asset_local('css/bootstrap.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/styles.css') ?>">

  <script src="<?= asset_local('js/jquery.min.js') ?>"></script>
  <script src="<?= asset_local('js/dataTables.min.js') ?>"></script>
  <script src="<?= asset_local('js/dataTables.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset_local('js/dataTables.buttons.min.js') ?>"></script>
  <script src="<?= asset_local('js/buttons.html5.min.js') ?>"></script>
  <script src="<?= asset_local('js/jszip.min.js') ?>"></script>
  <script src="<?= asset_local('js/pdfmake.min.js') ?>"></script>
  <script src="<?= asset_local('js/vfs_fonts.js') ?>"></script>

  <link rel="stylesheet" href="<?= asset_local('css/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/apexcharts.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/jsvectormap.min.css') ?>" />

</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
  <div class="app-wrapper">

    <?php
    $current_empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 0;
    $current_modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;
    $usuario_logueado_id = $_SESSION['usuario_id'];

    $sql_modulos = "SELECT DISTINCT
            e.empresa_id,
            e.empresa,
            m.modulo_id,
            m.modulo,
            m.modulo_url
        FROM conf__empresas e
        INNER JOIN conf__empresas_perfiles ep ON e.empresa_id = ep.empresa_id
        INNER JOIN conf__usuarios_perfiles up ON ep.empresa_perfil_id = up.empresa_perfil_id
        LEFT JOIN conf__empresas_modulos em ON e.empresa_id = em.empresa_id AND em.tabla_estado_registro_id = 1
        LEFT JOIN conf__modulos m ON em.modulo_id = m.modulo_id AND m.tabla_estado_registro_id = 1
        WHERE e.tabla_estado_registro_id = 1
        AND ep.tabla_estado_registro_id = 1
        AND up.tabla_estado_registro_id = 1
        AND up.usuario_id = $usuario_logueado_id
        ORDER BY e.empresa, m.modulo";

    $res_modulos = mysqli_query($conexion, $sql_modulos);
    $empresas_modulos = [];
    $nombre_empresa_actual = "";
    $nombre_modulo_actual = "";

    while ($row = mysqli_fetch_assoc($res_modulos)) {
      $eid = $row['empresa_id'];
      $mid = $row['modulo_id'];

      if (!isset($empresas_modulos[$eid])) {
        $empresas_modulos[$eid] = [
          'nombre' => $row['empresa'],
          'modulos' => []
        ];
      }

      if ($eid == $current_empresa_id) {
        $nombre_empresa_actual = $row['empresa'];
        if ($mid == $current_modulo_id) {
          $nombre_modulo_actual = $row['modulo'];
        }
      }

      if (!empty($mid)) {
        $empresas_modulos[$eid]['modulos'][] = [
          'id' => $mid,
          'nombre' => $row['modulo'],
          'url' => $row['modulo_url']
        ];
      }
    }

    if ($current_empresa_id > 0 && !isset($empresas_modulos[$current_empresa_id])) {
      echo "<script>
            alert('Acceso Denegado: No tienes permisos para esta empresa.');
            window.location.href = '" . url('index.php') . "';
        </script>";
      exit;
    }

    $paginas_permitidas = [];
    if ($current_empresa_id > 0) {
      $paginas_permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_logueado_id, $current_empresa_id);

      $pagina_actual_id = isset($pagina_idx) ? intval($pagina_idx) : 0;
      if ($pagina_actual_id > 0 && !isset($paginas_permitidas[$pagina_actual_id])) {
        echo "<script>
              alert('Acceso Denegado: No tienes permisos para acceder a esta página.');
              window.location.href = '" . url('index.php') . "';
          </script>";
        exit;
      }
    }
    ?>

    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
              <i class="bi bi-list"></i>
            </a>
          </li>

          <li class="nav-item d-none d-md-block">
            <span class="navbar-text fw-bold fs-5 ms-3 text-primary">
              <?= htmlspecialchars($nombre_empresa_actual) ?>
            </span>
          </li>
        </ul>

        <div class="ms-auto d-flex align-items-center">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#">
                <i class="bi bi-bell-fill"></i>
                <span class="navbar-badge badge text-bg-warning">15</span>
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <span class="dropdown-item dropdown-header">15 Notifications</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer"> See All Notifications </a>
              </div>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
              </a>
            </li>

            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <div
                  class="user-image rounded-circle shadow d-inline-flex justify-content-center align-items-center text-white"
                  style="width: 2.1rem; height: 2.1rem; background-color: <?= $color_fondo_avatar ?>; font-weight: bold; font-size: 0.9rem;">
                  <?= $iniciales ?>
                </div>

                <span class="d-none d-md-inline">
                  <?= htmlspecialchars($nombre_usuario_avatar) ?>
                </span>
              </a>

              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary" style="height: auto; padding-bottom: 20px;">
                  <div
                    class="rounded-circle shadow d-flex justify-content-center align-items-center text-white mx-auto mb-2"
                    style="width: 90px; height: 90px; background-color: <?= $color_fondo_avatar ?>; font-size: 2.5rem; font-weight: bold; border: 3px solid rgba(255,255,255,0.2);">
                    <?= $iniciales ?>
                  </div>

                  <p>
                    <?= htmlspecialchars($nombre_usuario_avatar) ?>
                    <small>Usuario del Sistema</small>
                  </p>
                </li>

                <li class="user-footer">
                  <a href="#" class="btn btn-default btn-flat">Perfil</a>
                  <a href="<?= url('logout.php') ?>" class="btn btn-default btn-flat float-end">Salir</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <a href="#" class="brand-link">
          <img src="<?= asset('img/logo.png') ?>" alt="Logo" class="brand-image opacity-75 shadow" />
          <span class="brand-text fw-light">Multigestion</span>
        </a>
      </div>

      <div class="sidebar-info px-3 py-3 border-bottom border-secondary" style="background: rgba(0,0,0,0.1);">

        <div class="mb-3">
          <label class="form-label text-white-50 mb-1"
            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Módulo Activo</label>
          <select class="form-select form-select-sm bg-dark text-light border-secondary shadow-sm"
            style="font-size: 0.85rem;" onchange="if(this.value) window.location.href=this.value;">
            <option value="">Seleccionar Módulo...</option>
            <?php
            if (isset($empresas_modulos[$current_empresa_id]) && !empty($empresas_modulos[$current_empresa_id]['modulos'])):
              foreach ($empresas_modulos[$current_empresa_id]['modulos'] as $mod):
                $ruta_modulo = $mod['url'];
                if (strpos($ruta_modulo, 'modules/') === false) {
                  $ruta_modulo = 'modules/' . ltrim($ruta_modulo, '/');
                }
                $url_mod = url($ruta_modulo) . "?empresa_id=" . $current_empresa_id . "&modulo_id=" . $mod['id'];
                $selected = ($mod['id'] == $current_modulo_id) ? 'selected' : '';
                ?>
                <option value="<?= $url_mod ?>" <?= $selected ?>>
                  <?= htmlspecialchars($mod['nombre']) ?>
                </option>
                <?php
              endforeach;
            endif;
            ?>
          </select>
        </div>

        <div>
          <label class="form-label text-white-50 mb-1"
            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Empresa</label>
          <select class="form-select form-select-sm bg-dark text-light border-secondary shadow-sm"
            style="font-size: 0.85rem;" onchange="if(this.value) window.location.href=this.value;">
            <option value="">Cambiar Empresa...</option>
            <?php foreach ($empresas_modulos as $eid => $data): ?>
              <?php
              if (empty($data['modulos']))
                continue;
              $primer_modulo = $data['modulos'][0];
              $ruta_modulo = $primer_modulo['url'];
              if (strpos($ruta_modulo, 'modules/') === false) {
                $ruta_modulo = 'modules/' . ltrim($ruta_modulo, '/');
              }
              $target_url = url($ruta_modulo) . "?empresa_id=" . $eid . "&modulo_id=" . $primer_modulo['id'];
              $selected = ($eid == $current_empresa_id) ? 'selected' : '';
              ?>
              <option value="<?= $target_url ?>" <?= $selected ?>>
                <?= htmlspecialchars($data['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
            aria-label="Main navigation" data-accordion="true" id="navigation">

            <?php
            // Antes se comparaba por subcadena contra REQUEST_URI (strpos), lo
            // que marcaba como activa cualquier página cuya url fuera sufijo
            // de otra (ej: "proveedores.php" queda "contenido" dentro de
            // "productos_proveedores.php" y las marcaba activas a ambas).
            // pagina_id es exacto y siempre viaja en el link del menú
            // (?pagina_id=X), así que es la comparación correcta.
            $current_pagina_id = intval($_GET['pagina_id'] ?? 0);
            $qs_sidebar = "&empresa_id=" . $current_empresa_id . "&modulo_id=" . $current_modulo_id;

            $modudo_idx = $current_modulo_id;

            if ($modudo_idx) {
              $sql = "SELECT conf__paginas.*, conf__iconos.icono_clase 
                      FROM conf__paginas 
                      LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                      WHERE conf__paginas.modulo_id = $modudo_idx 
                      AND conf__paginas.tabla_estado_registro_id = 1 
                      AND conf__paginas.padre_id = 0 
                      ORDER BY conf__paginas.orden";
              $res = mysqli_query($conexion, $sql);

              while ($row = mysqli_fetch_array($res)) {
                $pagina_id_actual = (int) $row['pagina_id'];

                $submenu_sql = "SELECT conf__paginas.*, conf__iconos.icono_clase
                                FROM conf__paginas
                                LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                                WHERE conf__paginas.padre_id = " . $pagina_id_actual . "
                                AND conf__paginas.tabla_estado_registro_id = 1
                                ORDER BY conf__paginas.orden";
                $submenu_res = mysqli_query($conexion, $submenu_sql);

                $tiene_hijos_definidos = mysqli_num_rows($submenu_res) > 0;

                $submenu_items = [];
                while ($submenu_row = mysqli_fetch_array($submenu_res)) {
                  if (isset($paginas_permitidas[(int) $submenu_row['pagina_id']])) {
                    $submenu_items[] = $submenu_row;
                  }
                }

                $has_submenu = count($submenu_items) > 0;

                if ($tiene_hijos_definidos) {
                  // Es un contenedor (tiene hijos definidos en conf__paginas): se muestra
                  // únicamente si al menos uno de esos hijos está habilitado para el usuario.
                  // No aplica el "acceso libre" de páginas sin funciones, porque acá lo que
                  // importa es si hay contenido habilitado debajo, no la página en sí.
                  if (!$has_submenu) {
                    continue;
                  }
                } else {
                  // Página hoja: se muestra según su propio permiso.
                  if (!isset($paginas_permitidas[$pagina_id_actual])) {
                    continue;
                  }
                }

                $is_active = false;
                if ($has_submenu) {
                  foreach ($submenu_items as $submenu_row) {
                    if ($current_pagina_id > 0 && (int) $submenu_row['pagina_id'] === $current_pagina_id) {
                      $is_active = true;
                      break;
                    }
                  }
                } else {
                  $is_active = ($current_pagina_id > 0 && $pagina_id_actual === $current_pagina_id);
                }

                $link_padre = $has_submenu ? '#' : $row['url'] . '?pagina_id=' . $pagina_id_actual . $qs_sidebar;
                ?>

                <li class="nav-item <?= $is_active ? 'menu-open' : '' ?>">
                  <a href="<?= $link_padre ?>" class="nav-link <?= $is_active && !$has_submenu ? 'active' : '' ?>">
                    <i class="nav-icon <?= $row['icono_clase'] ?>"></i>
                    <p>
                      <?= $row['pagina'] ?>
                      <?php if ($has_submenu): ?>
                        <i class="nav-arrow bi bi-chevron-right"></i>
                      <?php endif; ?>
                    </p>
                  </a>

                  <?php if ($has_submenu): ?>
                    <ul class="nav nav-treeview"
                      style="padding-left: 25px; margin-left: 10px; border-left: 2px solid #dee2e6;">
                      <?php foreach ($submenu_items as $submenu_row): ?>
                        <?php
                        $is_submenu_active = ($current_pagina_id > 0 && (int) $submenu_row['pagina_id'] === $current_pagina_id);
                        ?>
                        <li class="nav-item">
                          <a href="<?= $submenu_row['url'] ?>?pagina_id=<?= $submenu_row['pagina_id'] ?><?= $qs_sidebar ?>"
                            class="nav-link <?= $is_submenu_active ? 'active' : '' ?>" style="padding-left: 15px;">
                            <i class="nav-icon <?= $submenu_row['icono_clase'] ?>"></i>
                            <p><?= $submenu_row['pagina'] ?></p>
                            <?php if ($is_submenu_active): ?>
                              <span class="sr-only">(current)</span>
                            <?php endif; ?>
                          </a>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </li>
              <?php }
            }
            ?>
          </ul>
        </nav>
      </div>
    </aside>
    <main class="app-main">