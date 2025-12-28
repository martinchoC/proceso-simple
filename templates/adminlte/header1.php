<?php
// Al inicio del header1.php
require_once __DIR__ . '/../../config.php'; // Ajusta según la ubicación real


?>
<!doctype html>
<html lang="en">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Developsam | Multigestion</title>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <!--end::Accessibility Meta Tags-->
    <!--begin::Primary Meta Tags-->
    <meta name="title" content="Developsam | Multigestion" />
    <meta name="author" content="Developsam" />
    <meta
      name="description"
      content="Developsam"
    />
    
    <!--end::Primary Meta Tags-->
    <!--begin::Accessibility Features-->
    <!-- Skip links will be dynamically added by accessibility.js -->
    <meta name="supported-color-schemes" content="light dark" />

    
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
     <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- SweetAlert2 Theme -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css" rel="stylesheet" />
    
    <!-- AdminLTE CSS (YA INCLUYE Bootstrap) -->
    <link rel="stylesheet" href="<?= asset('css/adminlte.css') ?>" />
    
    <!-- OverlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    
     
    
    <!-- Botones de DataTables -->
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

    <!-- Librerías para exportar -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    
    <!--end::Accessibility Features-->
    <!--begin::Fonts-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
      integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
      crossorigin="anonymous"
      media="print"
      onload="this.media='all'"
    />
    <!--end::Fonts-->
    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="<?= asset('css/adminlte.css') ?>" />
    <!--end::Required Plugin(AdminLTE)-->
    <!-- apexcharts -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
      integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0="
      crossorigin="anonymous"
    />
    <!-- jsvectormap -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
      integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4="
      crossorigin="anonymous"
    />
  </head>
<style>
   /* Permite que el sidebar crezca */
.sidebar-wrapper {
    width: auto !important;
    min-width: 250px;
    white-space: nowrap;
    overflow-x: visible; /* asegura que no se corte el contenido */
}

/* Asegura que los textos ocupen solo lo necesario */
.sidebar-wrapper .nav-link p {
    display: inline-block;
    width: auto;
}
</style>  
  <!--end::Head-->
  <!--begin::Body-->
  <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <!--begin::App Wrapper-->
    <div class="app-wrapper">
      <!--begin::Header-->
      <nav class="app-header navbar navbar-expand bg-body">
        <!--begin::Container-->
        <div class="container-fluid">
          <!--begin::Start Navbar Links-->
                    <ul class="navbar-nav">
              <li class="nav-item">
                  <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                      <i class="bi bi-list"></i>
                  </a>
              </li>
              <?php
              $current_url_full = $_SERVER['REQUEST_URI'];
              $current_url_path = parse_url($current_url_full, PHP_URL_PATH);
              $current_url_base = basename($current_url_path);
              
              $sql = "SELECT 
                  e.empresa_id,
                  e.empresa,
                  m.modulo_id,
                  m.modulo,
                  m.modulo_url,
                  m.base_datos,
                  img.imagen_id
              FROM conf__empresas e
              LEFT JOIN conf__empresas_modulos em 
                  ON e.empresa_id = em.empresa_id 
                  AND em.tabla_estado_registro_id = 1
              LEFT JOIN conf__modulos m 
                  ON em.modulo_id = m.modulo_id 
                  AND m.tabla_estado_registro_id = 1
              LEFT JOIN conf__imagenes img 
                  ON m.imagen_id = img.imagen_id
              WHERE e.tabla_estado_registro_id = 1
              ORDER BY e.empresa, m.modulo";

              $result = mysqli_query($conexion, $sql);

              // Array para agrupar módulos por empresa
              $empresas_modulos = [];

              while ($row = mysqli_fetch_array($result)) {
                  $empresa_id = $row['empresa_id'];
                  
                  // Si no existe la empresa en el array, la inicializamos
                  if (!isset($empresas_modulos[$empresa_id])) {
                      $empresas_modulos[$empresa_id] = [
                          'empresa' => $row['empresa'],
                          'modulos' => []
                      ];
                  }
                  
                  // Si hay un módulo asociado, lo añadimos
                  if (!empty($row['modulo_id'])) {
                      $empresas_modulos[$empresa_id]['modulos'][] = [
                          'modulo_id' => $row['modulo_id'], // Añadido
                          'modulo' => $row['modulo'],
                          'modulo_url' => $row['modulo_url'],
                          'imagen_id' => $row['imagen_id'],
                          'base_datos' => $row['base_datos']
                      ];
                  }
              }

              // Ahora generamos el HTML organizado por empresas
              foreach ($empresas_modulos as $empresa_id => $datos_empresa) {
                  // Mostrar la empresa
                  echo '<div class="empresa-section">';
                  echo '<h4 class="empresa-title">' . htmlspecialchars($datos_empresa['empresa']) . '</h4>';
                  
                  // Mostrar los módulos de esta empresa
                  if (!empty($datos_empresa['modulos'])) {
                      echo '<ul class="nav flex-column">';
                      foreach ($datos_empresa['modulos'] as $modulo) {
                          echo '<li class="nav-item d-none d-md-block">';
                          
                          // OPCIÓN 1: Usando GET en la URL (más común)
                          $url_con_parametros = htmlspecialchars('../' . $modulo['modulo_url']) . 
                                              '?empresa_id=' . $empresa_id . 
                                              '&modulo_id=' . $modulo['modulo_id'];
                          
                          echo '<a href="' . $url_con_parametros . '" class="nav-link">';
                          echo htmlspecialchars($modulo['modulo']);
                          echo '</a>';
                          echo '</li>';
                      }
                      echo '</ul>';
                  } else {
                      echo '<p class="text-muted">No tiene módulos asignados</p>';
                  }
                  echo '</div>';
                  echo '<hr>';
              }
              ?>
          </ul>
          <!--end::Start Navbar Links-->
          <!--begin::End Navbar Links-->
          <ul class="navbar-nav ms-auto">
                      
            <!--begin::Notifications Dropdown Menu-->
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
            <!--end::Notifications Dropdown Menu-->
            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
              <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
              </a>
            </li>
            <!--end::Fullscreen Toggle-->
            <!--begin::User Menu Dropdown-->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img
                  src="./assets/img/user2-160x160.jpg"
                  class="user-image rounded-circle shadow"
                  alt="User Image"
                />
                <span class="d-none d-md-inline">Alexander Pierce</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!--begin::User Image-->
                <li class="user-header text-bg-primary">
                  <img
                    src="./assets/img/user2-160x160.jpg"
                    class="rounded-circle shadow"
                    alt="User Image"
                  />
                  <p>
                    Alexander Pierce - Web Developer
                    <small>Member since Nov. 2023</small>
                  </p>
                </li>
                <!--end::User Image-->
                <!--begin::Menu Body-->
                <li class="user-body">
                  <!--begin::Row-->
                  <div class="row">
                    <div class="col-4 text-center"><a href="#">Followers</a></div>
                    <div class="col-4 text-center"><a href="#">Sales</a></div>
                    <div class="col-4 text-center"><a href="#">Friends</a></div>
                  </div>
                  <!--end::Row-->
                </li>
                <!--end::Menu Body-->
                <!--begin::Menu Footer-->
                <li class="user-footer">
                  <a href="#" class="btn btn-default btn-flat">Profile</a>
                  <a href="#" class="btn btn-default btn-flat float-end">Sign out</a>
                </li>
                <!--end::Menu Footer-->
              </ul>
            </li>
            <!--end::User Menu Dropdown-->
          </ul>
          <!--end::End Navbar Links-->
        </div>
        <!--end::Container-->
      </nav>
      <!--end::Header-->
      <!--begin::Sidebar-->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand">
          <!--begin::Brand Link-->
          <a href="./index.html" class="brand-link">
            <!--begin::Brand Image-->
            <img
              src="<?= asset('img/developsam_logo.png') ?>"
              alt="AdminLTE Logo"
              class="brand-image opacity-75 shadow"
            />
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light">Multigestion</span>
            <!--end::Brand Text-->
          </a>
          <!--end::Brand Link-->
        </div>
        <!--end::Sidebar Brand-->
        <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
        <nav class="mt-2">
          <!--begin::Sidebar Menu-->
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
                      <a href="<?= $has_submenu ? '#' : $row['url'] ?>" class="nav-link <?= $is_active && !$has_submenu ? 'active' : '' ?>">
                          <i class="nav-icon <?= $row['icono_clase'] ?>"></i>
                          <p>
                              <?= $row['pagina'] ?>
                              <?php if ($has_submenu): ?>
                                  <i class="nav-arrow bi bi-chevron-<?= $is_active ? 'down' : 'right' ?>"></i>
                              <?php endif; ?>
                          </p>
                      </a>
                      
                      <?php if ($has_submenu): ?>
                      <!-- Submenú con mayor indentación -->
                      <ul class="nav nav-treeview" style="display: <?= $is_active ? 'block' : 'none'; ?>; padding-left: 25px; margin-left: 10px; border-left: 2px solid #dee2e6;">
                          <?php while ($submenu_row = mysqli_fetch_array($submenu_res)): ?>
                              <?php
                              $submenu_url_clean = trim($submenu_row['url'], './');
                              $is_submenu_active = (strpos($current_url_full, $submenu_url_clean) !== false);
                              ?>
                              <li class="nav-item">
                                  <a href="<?= $submenu_row['url'] ?>" class="nav-link <?= $is_submenu_active ? 'active' : '' ?>" style="padding-left: 15px;">
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
        <!--end::Sidebar Wrapper-->
      </aside>
      <!--end::Sidebar-->
      <!--begin::App Main-->
      