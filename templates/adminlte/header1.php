<?php
// Si la variable $ruta_assets no viene definida desde el archivo padre, asumimos raíz
// (Esto reemplaza tu función asset() para trabajar solo con PHP nativo y rutas relativas)
$ruta = $ruta_assets ?? ''; 

require_once __DIR__ . '/../../config.php'; 
?>
<!doctype html>
<html lang="es">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Developsam | Multigestion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    
    <script src="<?php echo $ruta; ?>assets/js/jquery.min.js"></script>

    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/bootstrap.min.css" />
    
    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/adminlte.min.css" />
    
    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/all.min.css" />
    
    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/buttons.dataTables.min.css" />
    
    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/sweetalert2-bootstrap-4.min.css" />
    <link rel="stylesheet" href="<?php echo $ruta; ?>assets/css/overlayscrollbars.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    <script src="<?php echo $ruta; ?>assets/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo $ruta; ?>assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo $ruta; ?>assets/js/dataTables.buttons.min.js"></script>
    <script src="<?php echo $ruta; ?>assets/js/buttons.html5.min.js"></script>
    <script src="<?php echo $ruta; ?>assets/js/jszip.min.js"></script>
    <script src="<?php echo $ruta; ?>assets/js/pdfmake.min.js"></script>
    <script src="<?php echo $ruta; ?>assets/js/vfs_fonts.js"></script>

    <style>
       .sidebar-wrapper {
           width: auto !important;
           min-width: 250px;
           white-space: nowrap;
           overflow-x: visible; 
       }
       .sidebar-wrapper .nav-link p {
           display: inline-block;
           width: auto;
       }
    </style>  
  </head>
  
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
             // TU LÓGICA ORIGINAL DE MENÚ SUPERIOR
             $current_url_full = $_SERVER['REQUEST_URI'];
             
             if(isset($conexion)){
                 $sql = "SELECT conf__modulos.*, conf__imagenes.imagen_id FROM conf__modulos 
                  LEFT JOIN conf__imagenes ON conf__modulos.imagen_id = conf__imagenes.imagen_id
                  WHERE conf__modulos.tabla_estado_registro_id=1
                  ORDER BY conf__modulos.modulo";
                  $result = mysqli_query($conexion, $sql);
                  
                  while ($row = mysqli_fetch_array($result)) {
                      // Usamos $ruta para armar el link correctamente desde cualquier nivel
                      $link = $ruta . $row['modulo_url'];
                      ?>
                      <li class="nav-item d-none d-md-block">
                          <a href="<?php echo $link; ?>" class="nav-link"><?php echo $row['modulo'];?></a>
                      </li>
                   <?php
                   }
             }
            ?>
          </ul>
          
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?php echo $ruta; ?>assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User Image" />
                <span class="d-none d-md-inline">Usuario</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="<?php echo $ruta; ?>assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User Image" />
                  <p>Usuario Sistema</p>
                </li>
                <li class="user-footer">
                  <a href="#" class="btn btn-default btn-flat float-end">Salir</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
          <a href="<?php echo $ruta; ?>index.php" class="brand-link">
            <img src="<?php echo $ruta; ?>assets/img/developsam_logo.png" alt="Logo" class="brand-image opacity-75 shadow" />
            <span class="brand-text fw-light">Multigestion</span>
          </a>
        </div>
        
        <div class="sidebar-wrapper">
            <nav class="mt-2">
              <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" id="navigation">
                  <?php
                  // TU LÓGICA ORIGINAL DE MENÚ LATERAL
                  if(isset($conexion) && isset($modudo_idx)){
                      $sql = "SELECT conf__paginas.*, conf__iconos.icono_clase FROM conf__paginas 
                      LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                      WHERE conf__paginas.modulo_id=$modudo_idx AND conf__paginas.tabla_estado_registro_id=1 AND conf__paginas.padre_id=0 
                      ORDER BY conf__paginas.orden";
                      $res = mysqli_query($conexion, $sql);
                      
                      while ($row = mysqli_fetch_array($res)) {
                          // Submenús
                          $submenu_sql = "SELECT conf__paginas.*, conf__iconos.icono_clase
                          FROM conf__paginas 
                          LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                          WHERE conf__paginas.padre_id = " . $row['pagina_id'] . " AND conf__paginas.tabla_estado_registro_id = 1 
                          ORDER BY conf__paginas.orden";
                          $submenu_res = mysqli_query($conexion, $submenu_sql);
                          $has_submenu = mysqli_num_rows($submenu_res) > 0;
                          
                          $is_active = false;
                          
                          // Lógica de activo (Misma que tenías)
                          if ($has_submenu) {
                              mysqli_data_seek($submenu_res, 0);
                              while ($submenu_row = mysqli_fetch_array($submenu_res)) {
                                  $submenu_url_clean = trim($submenu_row['url'], './');
                                  if (strpos($current_url_full, $submenu_url_clean) !== false) {
                                      $is_active = true;
                                      break;
                                  }
                              }
                              mysqli_data_seek($submenu_res, 0);
                          } else {
                              $menu_url_clean = trim($row['url'], './');
                              $is_active = (strpos($current_url_full, $menu_url_clean) !== false);
                          }

                          // Link principal (Usamos $ruta)
                          $href_principal = $has_submenu ? '#' : $ruta . $row['url'];
                          ?>
                          
                          <li class="nav-item <?= $is_active ? 'menu-open' : '' ?>">
                              <a href="<?= $href_principal ?>" class="nav-link <?= $is_active ? 'active' : '' ?>">
                                  <i class="nav-icon <?= $row['icono_clase'] ?>"></i>
                                  <p>
                                      <?= $row['pagina'] ?>
                                      <?php if ($has_submenu): ?>
                                          <i class="nav-arrow bi bi-chevron-right"></i>
                                      <?php endif; ?>
                                  </p>
                              </a>
                              
                              <?php if ($has_submenu): ?>
                              <ul class="nav nav-treeview">
                                  <?php while ($submenu_row = mysqli_fetch_array($submenu_res)): ?>
                                      <?php
                                      $submenu_url_clean = trim($submenu_row['url'], './');
                                      $is_submenu_active = (strpos($current_url_full, $submenu_url_clean) !== false);
                                      ?>
                                      <li class="nav-item">
                                          <a href="<?= $ruta . $submenu_row['url'] ?>" class="nav-link <?= $is_submenu_active ? 'active' : '' ?>">
                                              <i class="nav-icon <?= $submenu_row['icono_clase'] ?>"></i>
                                              <p><?= $submenu_row['pagina'] ?></p>
                                          </a>
                                      </li>
                                  <?php endwhile; ?>
                              </ul>
                              <?php endif; ?>
                          </li>
                      <?php } 
                  } ?>
              </ul>
            </nav>
        </div>
      </aside>