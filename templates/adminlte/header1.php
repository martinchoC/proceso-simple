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
    $usuario_logueado_id = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;
    $es_superusuario = in_array($usuario_logueado_id, USUARIOS_ACCESO_TOTAL, true);

    $current_empresa_id = (isset($_GET['empresa_id']) && intval($_GET['empresa_id']) > 0) 
        ? intval($_GET['empresa_id']) 
        : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);

    $current_modulo_id = (isset($_GET['modulo_id']) && intval($_GET['modulo_id']) > 0) 
        ? intval($_GET['modulo_id']) 
        : (isset($modudo_idx) && intval($modudo_idx) > 0 
            ? intval($modudo_idx) 
            : (isset($modulo_idx) && intval($modulo_idx) > 0 
                ? intval($modulo_idx) 
                : (isset($_SESSION['modulo_id']) ? intval($_SESSION['modulo_id']) : 0)));

    // Fallbacks automáticos de Módulo según URL si no viene fijado
    if ($current_modulo_id <= 0) {
        $uri_actual = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri_actual, '/modules/ml/') !== false || strpos($uri_actual, 'ml/') !== false) {
            $current_modulo_id = 3;
        } elseif (strpos($uri_actual, '/modules/conf/') !== false || strpos($uri_actual, 'conf/') !== false) {
            $current_modulo_id = 1;
        } elseif (strpos($uri_actual, '/modules/gestion/') !== false || strpos($uri_actual, 'gestion/') !== false) {
            $current_modulo_id = 2;
        }
    }

    $empresas_modulos = usuario_obtener_empresas_modulos($conexion, $usuario_logueado_id);

    // Fallback de Empresa si no viene fijada: empresa 2 (Casa Lucho) o la primera disponible
    if ($current_empresa_id <= 0) {
        if (isset($empresas_modulos[2])) {
            $current_empresa_id = 2;
        } elseif (!empty($empresas_modulos)) {
            $current_empresa_id = array_key_first($empresas_modulos);
        }
    }

    if ($current_empresa_id > 0) {
        $_SESSION['empresa_id'] = $current_empresa_id;
    }
    if ($current_modulo_id > 0) {
        $_SESSION['modulo_id'] = $current_modulo_id;
    }

    $nombre_empresa_actual = "";
    $nombre_modulo_actual = "";

    if (isset($empresas_modulos[$current_empresa_id])) {
      $nombre_empresa_actual = $empresas_modulos[$current_empresa_id]['nombre'];
      foreach ($empresas_modulos[$current_empresa_id]['modulos'] as $mod) {
        if ($mod['id'] == $current_modulo_id) {
          $nombre_modulo_actual = $mod['nombre'];
          break;
        }
      }
    }

    if (!$es_superusuario && $current_empresa_id > 0 && !isset($empresas_modulos[$current_empresa_id])) {
      echo "<script>
            alert('Acceso Denegado: No tienes permisos para esta empresa.');
            window.location.href = '" . url('index.php') . "';
        </script>";
      exit;
    }

    $paginas_permitidas = [];
    if ($current_empresa_id > 0 || $es_superusuario) {
      $paginas_permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_logueado_id, $current_empresa_id);

      $pagina_actual_id = isset($pagina_idx) ? intval($pagina_idx) : 0;
      if (!$es_superusuario && $pagina_actual_id > 0 && !isset($paginas_permitidas[$pagina_actual_id])) {
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

          <li class="nav-item d-flex align-items-center ms-2">
            <select class="form-select form-select-sm shadow-sm" style="font-size: 0.85rem; max-width: 220px;"
              aria-label="Empresa" onchange="if(this.value) window.location.href=this.value;">
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
          </li>

          <li class="nav-item d-flex align-items-center ms-2">
            <select class="form-select form-select-sm shadow-sm" style="font-size: 0.85rem; max-width: 200px;"
              aria-label="Módulo activo" onchange="if(this.value) window.location.href=this.value;">
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
          </li>

          <?php
          // Accesos directos del módulo activo (conf__paginas.es_acceso_directo = 1),
          // filtrados por los permisos del usuario en la empresa actual.
          $accesos_directos = [];
          if ($current_modulo_id > 0) {
            $sql_ad = "SELECT p.pagina_id, p.pagina, p.url, i.icono_clase
                       FROM conf__paginas p
                       LEFT JOIN conf__iconos i ON p.icono_id = i.icono_id
                       WHERE p.modulo_id = ?
                         AND p.es_acceso_directo = 1
                         AND p.tabla_estado_registro_id = 1
                       ORDER BY p.orden_acceso_directo IS NULL, p.orden_acceso_directo, p.orden";
            $stmt_ad = mysqli_prepare($conexion, $sql_ad);
            if ($stmt_ad) {
              mysqli_stmt_bind_param($stmt_ad, "i", $current_modulo_id);
              mysqli_stmt_execute($stmt_ad);
              $res_ad = mysqli_stmt_get_result($stmt_ad);
              while ($row_ad = mysqli_fetch_assoc($res_ad)) {
                if (!empty($row_ad['url']) && isset($paginas_permitidas[(int) $row_ad['pagina_id']])) {
                  $accesos_directos[] = $row_ad;
                }
              }
              mysqli_stmt_close($stmt_ad);
            } else {
              error_log("header1 accesos directos: " . mysqli_error($conexion));
            }
          }
          $qs_acceso = "&empresa_id=" . $current_empresa_id . "&modulo_id=" . $current_modulo_id;
          // Paleta de colores (RGB) que se asigna a cada acceso directo en forma cíclica
          $colores_acceso = ['13, 110, 253', '25, 135, 84', '253, 126, 20', '111, 66, 193', '214, 51, 132', '13, 202, 240', '220, 53, 69', '32, 201, 151'];
          foreach ($accesos_directos as $i_ad => $ad):
            $ad_activo = strpos($_SERVER['REQUEST_URI'], trim($ad['url'], './')) !== false;
            ?>
            <li class="nav-item<?= $i_ad === 0 ? ' ms-2' : '' ?>">
              <a class="nav-link acceso-directo<?= $ad_activo ? ' active' : '' ?>"
                href="<?= htmlspecialchars($ad['url']) ?>?pagina_id=<?= (int) $ad['pagina_id'] ?><?= $qs_acceso ?>"
                data-label="<?= htmlspecialchars($ad['pagina']) ?>" aria-label="<?= htmlspecialchars($ad['pagina']) ?>"
                style="--ad-i: <?= (int) $i_ad ?>; --ad-rgb: <?= $colores_acceso[$i_ad % count($colores_acceso)] ?>;">
                <i class="<?= htmlspecialchars($ad['icono_clase'] ?: 'bi bi-link-45deg') ?>"></i>
              </a>
            </li>
          <?php endforeach; ?>
          <?php if ($accesos_directos): ?>
            <style>
              /* Accesos directos de la barra superior */
              .acceso-directo {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.6rem;
                height: 2.6rem;
                margin: 0 2px;
                padding: 0 !important;
                border-radius: 0.75rem;
                color: rgb(var(--ad-rgb));
                isolation: isolate;
                transition: color .25s ease, transform .35s cubic-bezier(.34, 1.56, .64, 1);
                animation: ad-entrada .45s cubic-bezier(.34, 1.56, .64, 1) backwards;
                animation-delay: calc(var(--ad-i) * 60ms);
              }

              /* Fondo tipo "pastilla" que crece desde el centro */
              .acceso-directo::before {
                content: "";
                position: absolute;
                inset: 0;
                z-index: -1;
                border-radius: inherit;
                background: linear-gradient(135deg, rgba(var(--ad-rgb), .2), rgba(var(--ad-rgb), .07));
                box-shadow: 0 6px 16px -6px rgba(var(--ad-rgb), .6);
                transform: scale(.4);
                opacity: 0;
                transition: transform .35s cubic-bezier(.34, 1.56, .64, 1), opacity .25s ease;
              }

              /* Etiqueta con el nombre de la página, se desliza desde abajo */
              .acceso-directo::after {
                content: attr(data-label);
                position: absolute;
                top: calc(100% + 8px);
                left: 50%;
                z-index: 1080;
                padding: 4px 10px;
                border-radius: 6px;
                background: var(--bs-emphasis-color);
                color: var(--bs-body-bg);
                font-size: .75rem;
                font-weight: 500;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transform: translate(-50%, -6px) scale(.9);
                transition: opacity .2s ease, transform .3s cubic-bezier(.34, 1.56, .64, 1);
              }

              .acceso-directo i {
                font-size: 1.35rem;
                line-height: 1;
                transition: transform .35s cubic-bezier(.34, 1.56, .64, 1);
              }

              .acceso-directo:hover,
              .acceso-directo:focus-visible {
                color: rgb(var(--ad-rgb));
                transform: translateY(-2px);
              }

              .acceso-directo:hover::before,
              .acceso-directo:focus-visible::before {
                transform: scale(1);
                opacity: 1;
              }

              .acceso-directo:hover::after,
              .acceso-directo:focus-visible::after {
                opacity: 1;
                transform: translate(-50%, 0) scale(1);
                transition-delay: .15s;
              }

              .acceso-directo:hover i,
              .acceso-directo:focus-visible i {
                animation: ad-rebote .6s cubic-bezier(.34, 1.56, .64, 1);
                transform: scale(1.15);
              }

              .acceso-directo:active {
                transform: translateY(0) scale(.92);
                transition-duration: .1s;
              }

              /* Página actual: pastilla visible y punto indicador */
              .acceso-directo.active {
                color: rgb(var(--ad-rgb)) !important;
              }

              .acceso-directo.active::before {
                transform: scale(1);
                opacity: 1;
                box-shadow: none;
              }

              .acceso-directo.active i::after {
                content: "";
                position: absolute;
                bottom: 3px;
                left: 50%;
                width: 4px;
                height: 4px;
                margin-left: -2px;
                border-radius: 50%;
                background: rgb(var(--ad-rgb));
              }

              @keyframes ad-rebote {
                0% { transform: scale(1) rotate(0); }
                30% { transform: scale(1.25) rotate(-10deg); }
                60% { transform: scale(1.1) rotate(6deg); }
                100% { transform: scale(1.15) rotate(0); }
              }

              @keyframes ad-entrada {
                from { opacity: 0; transform: translateY(-8px) scale(.8); }
                to { opacity: 1; transform: none; }
              }

              @media (prefers-reduced-motion: reduce) {
                .acceso-directo,
                .acceso-directo::before,
                .acceso-directo::after,
                .acceso-directo i {
                  animation: none !important;
                  transition: opacity .15s ease !important;
                }

                .acceso-directo:hover,
                .acceso-directo:hover i {
                  transform: none;
                }
              }
            </style>
          <?php endif; ?>
        </ul>

        <div class="ms-auto d-flex align-items-center">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#">
                <i class="bi bi-bell-fill"></i>
                <span class="navbar-badge badge text-bg-warning">0</span>
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
                  <?php if ($es_superusuario): ?>
                    <span class="badge bg-danger ms-1" style="font-size: 0.7rem;"><i class="fas fa-shield-alt me-1"></i>Superadmin</span>
                  <?php endif; ?>
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
          <span class="brand-text fw-light"><?= htmlspecialchars($nombre_empresa_actual ?: 'Multigestion') ?></span>
        </a>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
            aria-label="Main navigation" data-accordion="true" id="navigation">

            <?php
            $current_url_full = $_SERVER['REQUEST_URI'];
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
                  if ($es_superusuario || isset($paginas_permitidas[(int) $submenu_row['pagina_id']])) {
                    $submenu_items[] = $submenu_row;
                  }
                }

                $has_submenu = count($submenu_items) > 0;

                if ($tiene_hijos_definidos) {
                  // Es un contenedor (tiene hijos definidos en conf__paginas): se muestra
                  // si al menos uno de esos hijos está habilitado, o incondicional para superadmin.
                  if (!$es_superusuario && !$has_submenu) {
                    continue;
                  }
                } else {
                  // Página hoja: se muestra según su propio permiso, o incondicional para superadmin.
                  if (!$es_superusuario && !isset($paginas_permitidas[$pagina_id_actual])) {
                    continue;
                  }
                }

                $is_active = false;
                if ($has_submenu) {
                  foreach ($submenu_items as $submenu_row) {
                    $submenu_url_clean = trim($submenu_row['url'], './');
                    if (strpos($current_url_full, $submenu_url_clean) !== false) {
                      $is_active = true;
                      break;
                    }
                  }
                } else {
                  $menu_url_clean = trim($row['url'], './');
                  $is_active = (strpos($current_url_full, $menu_url_clean) !== false);
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
                        $submenu_url_clean = trim($submenu_row['url'], './');
                        $is_submenu_active = (strpos($current_url_full, $submenu_url_clean) !== false);
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
