<?php
// Al inicio del header1.php
require_once __DIR__ . '/../../config.php'; // Ajusta según la ubicación real


?>
<!doctype html>
<html lang="en">

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

  <meta name="supported-color-schemes" content="light dark" />


  <link href="<?= asset_local('css/dataTables.bootstrap5.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/all.min.css') ?>" />

  <link href="<?= asset_local('css/sweetalert2-bootstrap-4.min.css') ?>" rel="stylesheet" />

  <link rel="stylesheet" href="<?= asset_local('css/adminlte.min.css') ?>" />

  <link rel="stylesheet" href="<?= asset_local('css/overlayscrollbars.min.css') ?>" />

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.0/font/bootstrap-icons.css">


  <link href="<?= asset_local('css/bootstrap.min.css') ?>" rel="stylesheet" />


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
<style>
  /* Permite que el sidebar crezca */
  .sidebar-wrapper {
    width: auto !important;
    min-width: 250px;
    white-space: nowrap;
    overflow-x: visible;
    /* asegura que no se corte el contenido */
  }

  /* Asegura que los textos ocupen solo lo necesario */
  .sidebar-wrapper .nav-link p {
    display: inline-block;
    width: auto;
  }

  /* Ocultar skip links de accesibilidad inyectados por adminlte.js */
  .skip-link {
    position: absolute !important;
    top: -100px !important;
    left: 0 !important;
    background: #007bff !important;
    color: white !important;
    padding: 8px 16px !important;
    z-index: 9999 !important;
    transition: top 0.3s !important;
    text-decoration: none !important;
    border-radius: 0 0 5px 5px !important;
    display: inline-block !important;
  }

  .skip-link:focus {
    top: 0 !important;
    outline: none !important;
  }

  /* Ocultar el contenedor de skip links si es necesario */
  .skip-links {
    position: absolute !important;
    z-index: 10000 !important;
  }
</style>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
  <div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
              <i class="bi bi-list"></i>
            </a>
          </li>
          <?php
          $current_empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 0;
          $current_modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;

          $sql_modulos = "SELECT 
                  e.empresa_id,
                  e.empresa,
                  m.modulo_id,
                  m.modulo,
                  m.modulo_url
              FROM conf__empresas e
              LEFT JOIN conf__empresas_modulos em ON e.empresa_id = em.empresa_id AND em.tabla_estado_registro_id = 1
              LEFT JOIN conf__modulos m ON em.modulo_id = m.modulo_id AND m.tabla_estado_registro_id = 1
              WHERE e.tabla_estado_registro_id = 1
              ORDER BY e.empresa, m.modulo";

          $res_modulos = mysqli_query($conexion, $sql_modulos);
          $empresas_modulos = [];
          $nombre_empresa_actual = "Multigestion";
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

          $titulo_display = htmlspecialchars($nombre_empresa_actual);
          if (!empty($nombre_modulo_actual)) {
            $titulo_display .= " - " . htmlspecialchars($nombre_modulo_actual);
          }
          ?>
          <li class="nav-item d-none d-md-block">
            <span class="navbar-text fw-bold fs-5 ms-3 text-primary">
              <?= $titulo_display ?>
            </span>
          </li>
        </ul>

        <div class="ms-auto d-flex align-items-center">
          <div class="input-group input-group-sm me-3" style="width: 250px;">
            <span class="input-group-text bg-primary text-white border-primary">
              <i class="bi bi-grid-3x3-gap-fill"></i>
            </span>
            <select class="form-select border-primary" onchange="if(this.value) window.location.href=this.value;">
              <option value="">Seleccionar Módulo...</option>
              <?php foreach ($empresas_modulos as $eid => $data): ?>
                <optgroup label="<?= htmlspecialchars($data['nombre']) ?>">
                  <?php foreach ($data['modulos'] as $mod): ?>
                    <?php
                    $target_url = "../" . $mod['url'] . "?empresa_id=" . $eid . "&modulo_id=" . $mod['id'];
                    $selected = ($eid == $current_empresa_id && $mod['id'] == $current_modulo_id) ? 'selected' : '';
                    ?>
                    <option value="<?= $target_url ?>" <?= $selected ?>>
                      <?= htmlspecialchars($mod['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <ul class="navbar-nav ms-auto">

            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#">
                <i class="bi bi-bell-fill"></i>
                <span class="navbar-badge badge text-bg-warning">15</span>
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <span class="dropdown-item dropdown-header">15 Notifications</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <i class="bi bi-envelope me-2"></i> 4 new messages
                  <span class="float-end text-secondary fs-7">3 mins</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <i class="bi bi-people-fill me-2"></i> 8 friend requests
                  <span class="float-end text-secondary fs-7">12 hours</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <i class="bi bi-file-earmark-fill me-2"></i> 3 new reports
                  <span class="float-end text-secondary fs-7">2 days</span>
                </a>
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
                <img src="<?= asset('img/user2-160x160.jpg') ?>" class="user-image rounded-circle shadow"
                  alt="User Image" />
                <span class="d-none d-md-inline">Alexander Pierce</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="<?= asset('img/user2-160x160.jpg') ?>" class="rounded-circle shadow" alt="User Image" />
                  <p>
                    Alexander Pierce - Web Developer
                    <small>Member since Nov. 2023</small>
                  </p>
                </li>
                <li class="user-body">
                  <div class="row">
                    <div class="col-4 text-center"><a href="#">Followers</a></div>
                    <div class="col-4 text-center"><a href="#">Sales</a></div>
                    <div class="col-4 text-center"><a href="#">Friends</a></div>
                  </div>
                </li>
                <li class="user-footer">
                  <a href="#" class="btn btn-default btn-flat">Profile</a>
                  <a href="#" class="btn btn-default btn-flat float-end">Sign out</a>
                </li>
              </ul>
            </li>
          </ul>
        </div> <!-- End ms-auto -->
      </div> <!-- End container-fluid -->
    </nav>
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <a href="./index.html" class="brand-link">
          <img src="<?= asset('img/developsam_logo.png') ?>" alt="AdminLTE Logo"
            class="brand-image opacity-75 shadow" />
          <span class="brand-text fw-light">Multigestion</span>
        </a>
      </div>
      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
            aria-label="Main navigation" data-accordion="false" id="navigation">

            <?php
            // Obtener la URL actual
            $current_url_full = $_SERVER['REQUEST_URI'];
            $current_url_path = parse_url($current_url_full, PHP_URL_PATH);
            $current_url_base = basename($current_url_path);

            $sql = "SELECT conf__paginas.*, conf__iconos.icono_clase FROM conf__paginas 
              LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
              WHERE conf__paginas.modulo_id=$modudo_idx AND conf__paginas.tabla_estado_registro_id=1 AND conf__paginas.padre_id=0 
              ORDER BY conf__paginas.orden";
            $res = mysqli_query($conexion, $sql);

            while ($row = mysqli_fetch_array($res)) {
              $submenu_sql = "SELECT conf__paginas.*, conf__iconos.icono_clase
                  FROM conf__paginas 
                  LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                  WHERE conf__paginas.padre_id = " . $row['pagina_id'] . " AND conf__paginas.tabla_estado_registro_id = 1 
                  ORDER BY conf__paginas.orden";
              $submenu_res = mysqli_query($conexion, $submenu_sql);
              $has_submenu = mysqli_num_rows($submenu_res) > 0;

              $is_active = false;
              $submenu_active = false;

              if ($has_submenu) {
                mysqli_data_seek($submenu_res, 0);
                while ($submenu_row = mysqli_fetch_array($submenu_res)) {
                  $submenu_url_clean = trim($submenu_row['url'], './');
                  if (strpos($current_url_full, $submenu_url_clean) !== false) {
                    $is_active = true;
                    $submenu_active = true;
                    break;
                  }
                }
                mysqli_data_seek($submenu_res, 0);
              } else {
                $menu_url_clean = trim($row['url'], './');
                $is_active = (strpos($current_url_full, $menu_url_clean) !== false);
              }
              ?>

              <li class="nav-item <?= $is_active ? 'menu-open' : '' ?>">
                <a href="<?= $has_submenu ? '#' : $row['url'] ?>"
                  class="nav-link <?= $is_active && !$has_submenu ? 'active' : '' ?>">
                  <i class="nav-icon <?= $row['icono_clase'] ?>"></i>
                  <p>
                    <?= $row['pagina'] ?>
                    <?php if ($has_submenu): ?>
                      <i class="nav-arrow bi bi-chevron-<?= $is_active ? 'down' : 'right' ?>"></i>
                    <?php endif; ?>
                  </p>
                </a>

                <?php if ($has_submenu): ?>
                  <ul class="nav nav-treeview"
                    style="display: <?= $is_active ? 'block' : 'none'; ?>; padding-left: 25px; margin-left: 10px; border-left: 2px solid #dee2e6;">
                    <?php while ($submenu_row = mysqli_fetch_array($submenu_res)): ?>
                      <?php
                      $submenu_url_clean = trim($submenu_row['url'], './');
                      $is_submenu_active = (strpos($current_url_full, $submenu_url_clean) !== false);
                      ?>
                      <li class="nav-item">
                        <a href="<?= $submenu_row['url'] ?>?pagina_id=<?= $submenu_row['pagina_id'] ?>"
                          class="nav-link <?= $is_submenu_active ? 'active' : '' ?>" style="padding-left: 15px;">
                          <i class="nav-icon <?= $submenu_row['icono_clase'] ?>"></i>
                          <p><?= $submenu_row['pagina'] ?></p>
                          <?php if ($is_submenu_active): ?>
                            <span class="sr-only">(current)</span>
                          <?php endif; ?>
                        </a>
                      </li>
                    <?php endwhile; ?>
                  </ul>
                <?php endif; ?>
              </li>

            <?php } ?>

          </ul>
        </nav>



      </div>
    </aside>
    <main class="app-main">