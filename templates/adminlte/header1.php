<?php
// templates/adminlte/header1.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/csrf.php';
require_once __DIR__ . '/../../core/contexto.php';
require_once __DIR__ . '/../../core/ui.php';
require_once __DIR__ . '/../../core/formato.php';
require_once __DIR__ . '/../../core/empresa_logo.php';

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
  <?php
  $logo_barra = emp_logo_barra($conexion, (int)$current_empresa_id); // Parámetros de Empresa (core/empresa_logo.php)
  $logo_fav = $logo_barra ? url($logo_barra) : emp_logo_inicial($conexion);
  ?>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($logo_fav) ?>">
  <link rel="shortcut icon" href="<?= htmlspecialchars($logo_fav) ?>">
  <link href="<?= asset_local('css/datatables-1.13.8/dataTables.bootstrap5.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/fontawesome-5.15.4/css/all.min.css') ?>" />
  <link href="<?= asset_local('css/sweetalert2-theme-bootstrap-4-5.0.27/bootstrap-4.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/admin-lte-4.0.0-beta2/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/overlayscrollbars-2.11.0/overlayscrollbars.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/bootstrap-icons-1.13.0/bootstrap-icons.css') ?>">
  <link href="<?= asset_local('css/bootstrap-5.3.3/bootstrap.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/styles.css') ?>">
  <link href="<?= asset_local('css/datatables-responsive-2.5.0/responsive.bootstrap5.min.css') ?>" rel="stylesheet" />
  <link rel="stylesheet" href="<?= asset_local('css/ui-simple.css') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/css/ui-simple.css') ?>">

  <script>window.APP_ASSETS_URL = <?= json_encode(asset_local('')) ?>;</script>
  <script src="<?= asset_local('js/jquery-3.6.0/jquery.min.js') ?>"></script>
  <script src="<?= asset_local('js/datatables-1.13.6/jquery.dataTables.min.js') ?>"></script>
  <script src="<?= asset_local('js/datatables-1.13.6/dataTables.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset_local('js/datatables-buttons-2.4.1/dataTables.buttons.min.js') ?>"></script>
  <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.html5.min.js') ?>"></script>
  <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>
  <script>
    // Todas las tablas son responsive (default más abajo). Antes de que Responsive arme cada tabla
    // (este handler va antes de cargar la extensión, así corre primero): la columna de acciones
    // nunca se oculta ("all") y las columnas "ui-detalle" van siempre al detalle desplegable ("none"), salvo
    // el ID ("ID" / "#"): se oculta del todo (sigue en la búsqueda y en las exportaciones), así una tabla cuyo
    // único dato secundario es el ID no muestra un ▸ que despliega solo eso.
    if (window.jQuery) {
      jQuery(document).on('preInit.dt', function (e, settings) {
        if (e.namespace !== 'dt') return;
        var cols = settings.aoColumns || [];
        var ocultar = [];
        cols.forEach(function (col, i) {
          var th = col.nTh;
          if (!th) return;
          var clases = ' ' + (col.sClass || col.className || '') + ' ' + th.className + ' ';
          var texto = jQuery.trim(jQuery(th).text());
          if (/\sui-detalle\s/.test(clases) && /^(id|#|#\s*id)$/i.test(texto)) {
            ocultar.push(i);
          } else if (/\sui-detalle\s/.test(clases)) {
            jQuery(th).addClass('none');
          } else if (/^(acci[oó]n(es)?|opciones)$/i.test(texto) || (texto === '' && i === cols.length - 1)) {
            jQuery(th).addClass('all');
          }
        });
        if (ocultar.length) {
          try { new jQuery.fn.dataTable.Api(settings).columns(ocultar).visible(false, false); }
          catch (err) { console.warn('No se pudo ocultar la columna ID', err); }
        }
      });
    }
  </script>
  <script src="<?= asset_local('js/datatables-responsive-2.5.0/dataTables.responsive.min.js') ?>"></script>
  <script src="<?= asset_local('js/datatables-responsive-2.5.0/responsive.bootstrap5.min.js') ?>"></script>
  <script src="<?= asset_local('js/jszip-3.10.1/jszip.min.js') ?>"></script>
  <script src="<?= asset_local('js/pdfmake-0.1.53/pdfmake.min.js') ?>"></script>
  <script src="<?= asset_local('js/pdfmake-0.1.53/vfs_fonts.js') ?>"></script>
  <script src="<?= asset_local('js/formato.js') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/js/formato.js') ?>"></script>

  <?php
  // En modules/gestion, modules/gestion_normal y modules/sindicatos las tablas con el dom por defecto van sin
  // los botones Copy/Excel/CSV/PDF (sin estilo) y con el "Buscar:" al lado del Agregar.
  $dtSinBotonesDefault = (bool) preg_match('#/modules/(gestion(_normal)?|sindicatos)/#', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
  ?>
  <script>
    if (window.jQuery && $.fn && $.fn.dataTable) {
      var dtSinBotonesDefault = <?= $dtSinBotonesDefault ? 'true' : 'false' ?>;
      $.extend(true, $.fn.dataTable.defaults, {
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        dom: (dtSinBotonesDefault
               ? '<"row align-items-center mb-2"<"col-12 text-end"f>>'
               : '<"row align-items-center mb-2"<"col-12 col-md-6"B><"col-12 col-md-6 text-end"f>>') +
             '<"row"<"col-12"tr>>' +
             '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
        language: {
          processing: "Procesando...",
          search: "Buscar:",
          lengthMenu: "Mostrar _MENU_ registros",
          info: "Página _PAGE_ de _PAGES_",
          infoEmpty: "Página 0 de 0",
          infoFiltered: "(filtrado de _MAX_ registros)",
          loadingRecords: "Cargando...",
          zeroRecords: "No se encontraron resultados",
          emptyTable: "Ningún dato disponible en esta tabla",
          paginate: {
            first: "Primero",
            previous: "Anterior",
            next: "Siguiente",
            last: "Último"
          }
        },
        infoCallback: function (settings, start, end, max, total, pre) {
          if (total === 0) return "Página 0 de 0";
          var api = this.api ? this.api() : new $.fn.dataTable.Api(settings);
          var info = api.page.info();
          var str = "Página " + (info.page + 1) + " de " + info.pages;
          if (total < max) {
            str += " (filtrado de " + max + " registros)";
          }
          return str;
        }
      });

      // Solo tablas que usan el dom por defecto (oInit es lo que pasó la página, sin los
      // defaults): el buscador se mueve al lado del botón Agregar de la misma card.
      if (dtSinBotonesDefault) {
        $(document).on('init.dt', function (e, settings) {
          if (!settings || !settings.oInit || settings.oInit.dom || settings.oInit.sDom) return;
          var $agregar = $(settings.nTable).closest('.card').find('#contenedor-boton-agregar, #btnNuevo, [id^="btnNuevo"], [id^="btnNueva"], [id^="btnSubir"]').first();
          if (!$agregar.length) {
            $agregar = $('#contenedor-boton-agregar, #btnNuevo, [id^="btnNuevo"], [id^="btnNueva"], [id^="btnSubir"]').first();
          }
          var $filtro = $(settings.nTableWrapper).find('.dataTables_filter').first();
          if (!$agregar.length || !$filtro.length) return;
          var $fila = $filtro.closest('.row');
          // Si la página destruye y vuelve a crear la tabla, el buscador movido antes quedó
          // fuera del wrapper (no se borra con él): se saca para no duplicarlo.
          $('.dt-filtro-movido').filter(function () { return $(this).data('tabla') === settings.sTableId; }).remove();
          $filtro.addClass('dt-filtro-movido d-inline-block ms-2 align-middle').data('tabla', settings.sTableId).insertAfter($agregar);
          $filtro.find('label').addClass('mb-0');
          // Fuera del wrapper se pierden los estilos de dataTables.bootstrap5 (input inline y
          // angosto), por eso las clases explícitas.
          $filtro.find('input').addClass('form-control form-control-sm d-inline-block w-auto ms-1');
          if (!$.trim($fila.text()) && !$fila.find('input, select, button').length) $fila.remove();
        });
      }
    }
  </script>


  <link rel="stylesheet" href="<?= asset_local('css/admin-lte-4.0.0-beta2/adminlte.min.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/apexcharts-3.37.1/apexcharts.css') ?>" />
  <link rel="stylesheet" href="<?= asset_local('css/jsvectormap-1.5.3/jsvectormap.min.css') ?>" />

  <script>
    // Ciberseguridad: Sanitización de la barra de navegación para evitar Information Disclosure (fuga de IDs y extensiones .php)
    if (window.history && window.history.replaceState) {
      try {
        var _locUrl = new URL(window.location.href);
        var _modificado = false;
        if (_locUrl.searchParams.has('empresa_id') || _locUrl.searchParams.has('modulo_id') || _locUrl.searchParams.has('pagina_id')) {
          _locUrl.searchParams.delete('empresa_id');
          _locUrl.searchParams.delete('modulo_id');
          _locUrl.searchParams.delete('pagina_id');
          _modificado = true;
        }
        var _path = _locUrl.pathname;
        if (_path.endsWith('.php') && !_path.endsWith('_ajax.php')) {
          _path = _path.slice(0, -4);
          _modificado = true;
        }
        if (_modificado) {
          var _limpiaUrl = _path + (_locUrl.search ? _locUrl.search : '') + _locUrl.hash;
          window.history.replaceState({}, document.title, _limpiaUrl);
        }
      } catch (e) {}
    }
  </script>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary<?= !empty($ui_simple) ? ' ui-simple' : '' ?>">
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
        } elseif (strpos($uri_actual, '/modules/gestion_normal/') !== false || strpos($uri_actual, 'gestion_normal/') !== false) {
            $current_modulo_id = 5;
        } elseif (strpos($uri_actual, '/modules/gestion/') !== false || strpos($uri_actual, 'gestion/') !== false) {
            $current_modulo_id = 2;
        } elseif (strpos($uri_actual, '/modules/estudio_contable/') !== false || strpos($uri_actual, 'estudio_contable/') !== false) {
            $current_modulo_id = 4;
        } elseif (strpos($uri_actual, '/modules/sindicatos/') !== false || strpos($uri_actual, 'sindicatos/') !== false) {
            $current_modulo_id = 6;
        }
    }

    $empresas_modulos = usuario_obtener_empresas_modulos($conexion, $usuario_logueado_id);

    // Fallback de Empresa si no viene fijada
    if ($current_empresa_id <= 0) {
        if ($current_modulo_id == 6) {
            // Si navegamos en Sindicatos, priorizar una empresa con Módulo 6 activo
            foreach ($empresas_modulos as $eid => $edata) {
                foreach ($edata['modulos'] as $emod) {
                    if ($emod['id'] == 6) {
                        $current_empresa_id = $eid;
                        break 2;
                    }
                }
            }
        }
        if ($current_empresa_id <= 0) {
            if (isset($empresas_modulos[2])) {
                $current_empresa_id = 2;
            } elseif (!empty($empresas_modulos)) {
                $current_empresa_id = array_key_first($empresas_modulos);
            }
        }
    }

    if ($current_empresa_id > 0) {
        $_SESSION['empresa_id'] = $current_empresa_id;
    }
    // Empresa activa para los .js de las páginas (nunca hardcodear la empresa en el JS)
    echo '<script>window.EMPRESA_ID = ' . (int)$current_empresa_id . ';</script>';
    // Formato de fechas y números de la empresa (conf__empresas, core/formato.php): Fmt en el JS, fmt_*() en PHP
    $app_formato = fmt_config_js(fmt_usar_empresa($conexion, (int)$current_empresa_id));
    echo '<script>window.APP_FORMATO = ' . json_encode($app_formato, JSON_UNESCAPED_UNICODE) . '; if (window.Fmt) Fmt.configurar(window.APP_FORMATO);</script>';
    // Encabezado de los modales con los colores del logo de la empresa (conf__empresas, core/empresa_logo.php)
    echo '<style id="tema-empresa">' . emp_logo_css(emp_logo_tema($conexion, (int)$current_empresa_id)) . '</style>';
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

      $pagina_actual_id = isset($pagina_idx) ? intval($pagina_idx) : (isset($pagina_id) ? intval($pagina_id) : 0);
      // Si la página no definió explícitamente $pagina_idx, se resuelve automáticamente por su URL y módulo
      if ($pagina_actual_id <= 0 && $current_modulo_id > 0) {
        $script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script_actual !== '' && $script_actual !== 'index.php') {
          $pagina_actual_id = pagina_id_por_url($conexion, (int)$current_modulo_id, $script_actual);
        }
      }
      $pagina_idx = $pagina_actual_id;
      $pagina_id = $pagina_actual_id;
      $GLOBALS['pagina_idx'] = $pagina_actual_id;
      $GLOBALS['pagina_id'] = $pagina_actual_id;

      if ($pagina_actual_id > 0) {
        echo '<script>window.PAGINA_ID = ' . (int)$pagina_actual_id . '; window.PAGINA_IDX = ' . (int)$pagina_actual_id . ';</script>';
      }

      if (!$es_superusuario && $pagina_actual_id > 0 && !isset($paginas_permitidas[$pagina_actual_id])) {
        echo "<script>
              alert('Acceso Denegado: No tienes permisos para acceder a esta página.');
              window.location.href = '" . url('index.php') . "';
          </script>";
        exit;
      }
      // Botones marcados data-funcion="accion_js" que el perfil no tiene en esta página: ocultos (core/permisos.php)
      if ($pagina_actual_id > 0) {
        permisos_fijar_contexto($usuario_logueado_id, (int)$current_empresa_id);
        $css_funciones_ocultas = permisos_css_funciones_ocultas($conexion, $pagina_actual_id);
        if ($css_funciones_ocultas !== '') {
          echo '<style id="funciones-ocultas">' . $css_funciones_ocultas . '</style>';
        }
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

          <?php
          // Empresa y módulo activos en un solo botón; el menú lista los módulos de la empresa
          // y las demás empresas del usuario (cada una entra por su primer módulo).
          // Ciberseguridad: el cambio de contexto se procesa de forma segura vía core/contexto.php con CSRF
          // y redirección 303 a la URL limpia sin parámetros sensibles visibles.
          $ui_url_modulo = function ($empresa_id, $mod) {
            return url('core/contexto.php') . '?empresa_id=' . (int)$empresa_id . '&modulo_id=' . (int)$mod['id'] . '&token=' . csrf_token();
          };
          $ui_modulos_actuales = $empresas_modulos[$current_empresa_id]['modulos'] ?? [];
          ?>
          <li class="nav-item dropdown ms-1">
            <a class="nav-link dropdown-toggle ui-contexto" data-bs-toggle="dropdown" href="#" role="button"
              aria-expanded="false" title="Cambiar de empresa o de módulo">
              <span class="ui-contexto-empresa"><?= htmlspecialchars($nombre_empresa_actual ?: 'Elegí una empresa') ?></span>
              <?php if ($nombre_modulo_actual !== ''): ?>
                <span class="ui-contexto-modulo d-none d-sm-inline"><?= htmlspecialchars($nombre_modulo_actual) ?></span>
              <?php endif; ?>
            </a>
            <div class="dropdown-menu ui-contexto-menu">
              <?php if ($ui_modulos_actuales): ?>
                <h6 class="dropdown-header">Módulos</h6>
                <?php foreach ($ui_modulos_actuales as $mod): ?>
                  <a class="dropdown-item<?= $mod['id'] == $current_modulo_id ? ' active' : '' ?>"
                    href="<?= htmlspecialchars($ui_url_modulo($current_empresa_id, $mod)) ?>"><?= htmlspecialchars($mod['nombre']) ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php
              $ui_otras_empresas = array_filter($empresas_modulos, function ($data, $eid) use ($current_empresa_id) {
                return $eid != $current_empresa_id && !empty($data['modulos']);
              }, ARRAY_FILTER_USE_BOTH);
              ?>
              <?php if ($ui_otras_empresas): ?>
                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Cambiar de empresa</h6>
                <?php foreach ($ui_otras_empresas as $eid => $data): ?>
                  <a class="dropdown-item" href="<?= htmlspecialchars($ui_url_modulo($eid, $data['modulos'][0])) ?>"><?= htmlspecialchars($data['nombre']) ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </li>

          <?php
          // Helper para normalizar enlaces de páginas en el menú y accesos directos:
          // Si la URL en base de datos ya contiene 'modules/' o inicia con '/', se resuelve absoluta con url() para
          // evitar duplicaciones como modules/conf/modules/conf/ al navegar desde un subdirectorio.
          // Si es relativa simple (ej. 'usuarios.php' o 'auditoria.php'), se remueve .php manteniéndola relativa al módulo.
          $fn_normalizar_link_pagina = function (string $url_db): string {
            $url_limpia = trim($url_db);
            if ($url_limpia === '' || $url_limpia === '#') {
              return '#';
            }
            if (strpos($url_limpia, 'modules/') !== false || strpos($url_limpia, '/') === 0) {
              return url($url_limpia);
            }
            return preg_replace('/\.php$/i', '', $url_limpia);
          };

          // Accesos directos del módulo activo (conf__paginas.es_acceso_directo = 1),
          // filtrados por los permisos del usuario en la empresa actual. Son fijos por módulo: la
          // personalización de la pantalla de inicio (core/dashboard/) no los cambia.
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
          $qs_acceso = "";
          $req_uri_limpia = preg_replace('/\.php(\?.*)?$/i', '$1', (string)($_SERVER['REQUEST_URI'] ?? ''));
          foreach ($accesos_directos as $i_ad => $ad):
            $ad_url_sin_php = $fn_normalizar_link_pagina((string)$ad['url']);
            $ad_url_check = trim(preg_replace('/\.php$/i', '', (string)$ad['url']), './');
            $ad_activo = ($ad_url_check !== '' && strpos($req_uri_limpia, $ad_url_check) !== false);
            ?>
            <li class="nav-item d-none d-lg-block<?= $i_ad === 0 ? ' ms-2' : '' ?>">
              <a class="nav-link acceso-directo<?= $ad_activo ? ' active' : '' ?>"
                href="<?= htmlspecialchars($ad_url_sin_php) ?>"
                data-label="<?= htmlspecialchars($ad['pagina']) ?>" aria-label="<?= htmlspecialchars($ad['pagina']) ?>"
                style="--ad-i: <?= (int) $i_ad ?>;">
                <i class="<?= htmlspecialchars($ad['icono_clase'] ?: 'bi bi-link-45deg') ?>"></i>
              </a>
            </li>
          <?php endforeach; ?>
          <?php if ($accesos_directos): ?>
            <style>
              /* Accesos directos de la barra superior (fondo con los colores del logo, ui-simple.css):
                 íconos blancos suaves, se resaltan al pasar el mouse */
              .acceso-directo {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.4rem;
                height: 2.4rem;
                margin: 0 1px;
                padding: 0 !important;
                border-radius: 0.5rem;
                color: rgba(255, 255, 255, .78) !important;
                transition: color .15s ease, background-color .15s ease;
              }
              .acceso-directo i { font-size: 1.15rem; line-height: 1; }
              .acceso-directo:hover,
              .acceso-directo:focus-visible { color: #fff !important; background: rgba(255, 255, 255, .14); }
              .acceso-directo.active { color: #fff !important; background: rgba(255, 255, 255, .22); box-shadow: inset 0 -2px 0 #fff; }
              /* Nombre de la página debajo del ícono */
              .acceso-directo::after {
                content: attr(data-label);
                position: absolute;
                top: calc(100% + 6px);
                left: 50%;
                z-index: 1080;
                padding: 3px 8px;
                border-radius: 4px;
                background: #fff;
                color: var(--ui-logo-marino, #03112f);
                box-shadow: 0 2px 8px rgba(0, 0, 0, .18);
                font-size: .75rem;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transform: translateX(-50%);
                transition: opacity .15s ease;
              }
              .acceso-directo:hover::after,
              .acceso-directo:focus-visible::after { opacity: 1; transition-delay: .2s; }
            </style>
          <?php endif; ?>
        </ul>

        <div class="ms-auto d-flex align-items-center">
          <ul class="navbar-nav ms-auto">
            <?php
            // Alertas de reposición de stock (página 113): solo si el usuario tiene esa página.
            $alertas_stock_visible = $current_empresa_id > 0 && ($es_superusuario || isset($paginas_permitidas[113]));
            $alertas_stock_abiertas = 0;
            if ($alertas_stock_visible && file_exists(__DIR__ . '/../../modules/gestion/stock_alertas_model.php')) {
              try {
                require_once __DIR__ . '/../../modules/gestion/stock_alertas_model.php';
                $alertas_stock_abiertas = salContarAbiertas($conexion, (int) $current_empresa_id, (int) $usuario_logueado_id);
              } catch (Throwable $e) {
                error_log('header1 alertas de stock: ' . $e->getMessage());
              }
            }
            ?>
            <?php if ($alertas_stock_visible): ?>
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#" title="Alertas de reposición de stock">
                <i class="bi bi-bell-fill"></i>
                <?php if ($alertas_stock_abiertas > 0): ?>
                  <span class="navbar-badge badge text-bg-warning"><?= $alertas_stock_abiertas ?></span>
                <?php endif; ?>
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <span class="dropdown-item dropdown-header">
                  <?= $alertas_stock_abiertas > 0
                    ? $alertas_stock_abiertas . ' producto' . ($alertas_stock_abiertas === 1 ? '' : 's') . ' bajo el stock mínimo'
                    : 'Sin alertas de reposición' ?>
                </span>
                <div class="dropdown-divider"></div>
                <a href="<?= url('modules/gestion/stock_alertas.php') ?>" class="dropdown-item dropdown-footer">Ver alertas de reposición</a>
              </div>
            </li>
            <?php endif; ?>
            <li class="nav-item d-none d-md-block">
              <a class="nav-link" href="#" data-lte-toggle="fullscreen" title="Pantalla completa">
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
                    <span class="ui-rol ms-1">Superadmin</span>
                  <?php endif; ?>
                </span>
              </a>

              <ul class="dropdown-menu dropdown-menu-end ui-usuario-menu">
                <li class="px-3 py-2">
                  <div class="fw-semibold"><?= htmlspecialchars($nombre_usuario_avatar) ?></div>
                  <div class="small text-body-secondary"><?= $es_superusuario ? 'Superadmin' : 'Usuario' ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Salir</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <a href="#" class="brand-link">
          <?php if ($logo_barra): ?>
            <img src="<?= htmlspecialchars(url($logo_barra)) ?>" alt="Logo" class="brand-image brand-image-empresa shadow" />
          <?php else: ?>
            <img src="<?= htmlspecialchars(emp_logo_inicial($conexion)) ?>" alt="Logo" class="brand-image brand-image-empresa shadow" />
          <?php endif; ?>
          <span class="brand-text fw-light"><?= htmlspecialchars($nombre_empresa_actual ?: 'Multigestion') ?></span>
        </a>
      </div>

      <!-- Buscador / Filtro del menú lateral -->
      <div class="sidebar-filter-wrapper">
        <div class="input-group input-group-sm">
          <span class="input-group-text" id="sidebar-filter-icon">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" id="sidebar-menu-filter" class="form-control"
            placeholder="Filtrar menú..." aria-label="Filtrar menú" autocomplete="off" spellcheck="false">
          <button class="btn btn-clear" type="button" id="sidebar-menu-filter-clear"
            title="Limpiar filtro" style="display: none;">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
            aria-label="Main navigation" data-accordion="true" id="navigation">

            <?php
            $current_url_full = $_SERVER['REQUEST_URI'];
            $qs_sidebar = "";

            $modudo_idx = $current_modulo_id;

            // Páginas destacadas en el menú (conf/paginas.php: menu_estilo, menu_color_id, menu_etiqueta; script
            // 20261106_paginas_menu_destacado_01.sql). El color sale de conf__colores: su color_clase (btn-success,
            // btn-outline-danger...) da el color de Bootstrap. Si las columnas no existen todavía, el menú queda igual.
            $menu_colores = [];
            $res_mc = mysqli_query($conexion, "SELECT color_id, color_clase FROM conf__colores");
            if ($res_mc) {
              while ($mc = mysqli_fetch_assoc($res_mc)) {
                if (preg_match('/^btn-(?:outline-)?(primary|secondary|success|danger|warning|info|light|dark)$/', (string) $mc['color_clase'], $mm)) {
                  $menu_colores[(int) $mc['color_id']] = $mm[1];
                }
              }
            }
            /** @return array{0:string,1:string,2:string} [clases del link, atributo style, HTML de la etiqueta] */
            $menu_destacado = function (array $fila) use ($menu_colores): array {
              $estilo = strtoupper(trim((string) ($fila['menu_estilo'] ?? '')));
              $etiqueta = trim((string) ($fila['menu_etiqueta'] ?? ''));
              $color = $menu_colores[(int) ($fila['menu_color_id'] ?? 0)] ?? 'primary';
              $clases = in_array($estilo, ['RESALTADO', 'BORDE'], true) ? ' menu-destacado menu-destacado-' . strtolower($estilo) : '';
              $style = ($clases !== '' || $etiqueta !== '') ? ' style="--menu-dest-rgb: var(--bs-' . $color . '-rgb);"' : '';
              $badge = $etiqueta !== '' ? '<span class="nav-badge badge text-bg-' . $color . ' menu-etiqueta">' . htmlspecialchars($etiqueta) . '</span>' : '';
              return [$clases, $style, $badge];
            };

            if ($modudo_idx) {
              $sql = "SELECT conf__paginas.*, conf__iconos.icono_clase 
                      FROM conf__paginas 
                      LEFT JOIN conf__iconos ON conf__paginas.icono_id = conf__iconos.icono_id
                      WHERE conf__paginas.modulo_id = $modudo_idx 
                      AND conf__paginas.tabla_estado_registro_id = 1 
                      ORDER BY conf__paginas.orden";
              $res = mysqli_query($conexion, $sql);

              $padres = [];
              $hijos_por_padre = [];
              if ($res) {
                while ($prow = mysqli_fetch_array($res)) {
                  $pid = (int) $prow['padre_id'];
                  if ($pid === 0) {
                    $padres[] = $prow;
                  } else {
                    $hijos_por_padre[$pid][] = $prow;
                  }
                }
              }

              foreach ($padres as $row) {
                $pagina_id_actual = (int) $row['pagina_id'];
                $hijos_definidos = $hijos_por_padre[$pagina_id_actual] ?? [];
                $tiene_hijos_definidos = !empty($hijos_definidos);

                $submenu_items = [];
                foreach ($hijos_definidos as $submenu_row) {
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
                $current_url_full = preg_replace('/\.php(\?.*)?$/i', '$1', (string)($_SERVER['REQUEST_URI'] ?? ''));
                if ($has_submenu) {
                  foreach ($submenu_items as $submenu_row) {
                    $submenu_url_clean = preg_replace('/\.php$/i', '', trim((string) ($submenu_row['url'] ?? ''), './'));
                    if ($submenu_url_clean !== '' && strpos($current_url_full, $submenu_url_clean) !== false) {
                      $is_active = true;
                      break;
                    }
                  }
                } else {
                  $menu_url_clean = preg_replace('/\.php$/i', '', trim((string) ($row['url'] ?? ''), './'));
                  $is_active = ($menu_url_clean !== '' && strpos($current_url_full, $menu_url_clean) !== false);
                }

                $link_padre = $has_submenu ? '#' : $fn_normalizar_link_pagina((string)$row['url']);

                // Secciones del menú para el desplegable de las migas (ui_encabezado de core/ui.php)
                if ($has_submenu) {
                  $ui_sec = ['nombre' => preg_replace('/^Gesti[oó]n\s*-\s*/u', '', $row['pagina']), 'paginas' => []];
                  foreach ($submenu_items as $submenu_row) {
                    $submenu_url_clean = preg_replace('/\.php$/i', '', trim((string) ($submenu_row['url'] ?? ''), './'));
                    $ui_sec['paginas'][] = [
                      'nombre' => preg_replace('/^Gesti[oó]n\s*-\s*/u', '', $submenu_row['pagina']),
                      'url' => $fn_normalizar_link_pagina((string)$submenu_row['url']),
                      'icono' => (string) $submenu_row['icono_clase'],
                      'activa' => ($submenu_url_clean !== '' && strpos($current_url_full, $submenu_url_clean) !== false),
                    ];
                    $GLOBALS['ui_menu_seccion_de_pagina'][(int) $submenu_row['pagina_id']] = count($GLOBALS['ui_menu_secciones'] ?? []);
                  }
                  $GLOBALS['ui_menu_secciones'][] = $ui_sec;
                }
                ?>

                <?php [$md_clases, $md_style, $md_badge] = $menu_destacado($row); ?>
                <li class="nav-item <?= $is_active ? 'menu-open' : '' ?>">
                  <a href="<?= $link_padre ?>" class="nav-link <?= $is_active && !$has_submenu ? 'active' : '' ?><?= $md_clases ?>"<?= $md_style ?>>
                    <i class="nav-icon <?= $row['icono_clase'] ?>"></i>
                    <p>
                      <?= htmlspecialchars(preg_replace('/^Gesti[oó]n\s*-\s*/u', '', $row['pagina'])) ?>
                      <?= $has_submenu ? '' : $md_badge ?>
                      <?php if ($has_submenu): ?>
                        <i class="nav-arrow bi bi-chevron-right"></i>
                      <?php endif; ?>
                    </p>
                  </a>

                  <?php if ($has_submenu): ?>
                    <ul class="nav nav-treeview">
                      <?php foreach ($submenu_items as $submenu_row): ?>
                        <?php
                        $submenu_url_clean = preg_replace('/\.php$/i', '', trim((string) ($submenu_row['url'] ?? ''), './'));
                        $is_submenu_active = ($submenu_url_clean !== '' && strpos($current_url_full, $submenu_url_clean) !== false);
                        [$md_clases, $md_style, $md_badge] = $menu_destacado($submenu_row);
                        ?>
                        <li class="nav-item">
                          <a href="<?= htmlspecialchars($fn_normalizar_link_pagina((string)$submenu_row['url'])) ?>"
                            class="nav-link <?= $is_submenu_active ? 'active' : '' ?><?= $md_clases ?>"<?= $md_style ?>>
                            <i class="nav-icon <?= $submenu_row['icono_clase'] ?>"></i>
                            <p><?= htmlspecialchars(preg_replace('/^Gesti[oó]n\s*-\s*/u', '', $submenu_row['pagina'])) ?><?= $md_badge ?></p>
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
            <li id="sidebar-menu-empty" class="nav-item text-center text-secondary py-3" style="display: none;">
              <small><i class="bi bi-search me-1"></i> No se encontraron páginas</small>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <script>
      (function() {
        document.addEventListener('DOMContentLoaded', function() {
          const filterInput = document.getElementById('sidebar-menu-filter');
          const clearBtn = document.getElementById('sidebar-menu-filter-clear');
          const navMenu = document.getElementById('navigation');
          const emptyMsg = document.getElementById('sidebar-menu-empty');

          if (!filterInput || !navMenu) return;

          function normalize(text) {
            return (text || '')
              .toLowerCase()
              .normalize('NFD')
              .replace(/[\u0300-\u036f]/g, '')
              .trim();
          }

          const parentItems = Array.from(navMenu.querySelectorAll(':scope > li.nav-item:not(#sidebar-menu-empty)'));
          parentItems.forEach(function(li) {
            li.dataset.originallyOpen = li.classList.contains('menu-open') ? '1' : '0';
          });

          function doFilter() {
            const rawVal = filterInput.value;
            const val = normalize(rawVal);
            const tokens = val.split(/\s+/).filter(Boolean);

            if (clearBtn) {
              clearBtn.style.display = rawVal.length > 0 ? '' : 'none';
            }

            if (tokens.length === 0) {
              parentItems.forEach(function(li) {
                li.style.display = '';
                const treeview = li.querySelector('.nav-treeview');
                if (treeview) {
                  const childItems = treeview.querySelectorAll(':scope > li.nav-item');
                  childItems.forEach(function(childLi) {
                    childLi.style.display = '';
                  });
                  if (li.dataset.originallyOpen === '1') {
                    li.classList.add('menu-open');
                    treeview.style.removeProperty('display');
                  } else {
                    li.classList.remove('menu-open');
                    treeview.style.display = 'none';
                  }
                }
              });

              if (emptyMsg) emptyMsg.style.display = 'none';
              return;
            }

            let totalVisible = 0;

            parentItems.forEach(function(li) {
              const treeview = li.querySelector('.nav-treeview');
              const parentLink = li.querySelector(':scope > a.nav-link');
              const parentTitle = parentLink ? parentLink.innerText : '';
              const normParentTitle = normalize(parentTitle);

              if (treeview) {
                const childItems = Array.from(treeview.querySelectorAll(':scope > li.nav-item'));
                let matchingChildrenCount = 0;

                const parentMatches = tokens.every(function(t) {
                  return normParentTitle.includes(t);
                });

                childItems.forEach(function(childLi) {
                  const childLink = childLi.querySelector('a.nav-link');
                  const childTitle = childLink ? childLink.innerText : '';
                  const normChildTitle = normalize(childTitle);
                  const combined = normParentTitle + ' ' + normChildTitle;

                  const childMatches = parentMatches || tokens.every(function(t) {
                    return combined.includes(t);
                  });

                  if (childMatches) {
                    childLi.style.display = '';
                    matchingChildrenCount++;
                  } else {
                    childLi.style.display = 'none';
                  }
                });

                if (matchingChildrenCount > 0) {
                  li.style.display = '';
                  li.classList.add('menu-open');
                  treeview.style.display = 'block';
                  totalVisible += matchingChildrenCount;
                } else {
                  li.style.display = 'none';
                  li.classList.remove('menu-open');
                  treeview.style.display = 'none';
                }
              } else {
                const itemMatches = tokens.every(function(t) {
                  return normParentTitle.includes(t);
                });

                if (itemMatches) {
                  li.style.display = '';
                  totalVisible++;
                } else {
                  li.style.display = 'none';
                }
              }
            });

            if (emptyMsg) {
              emptyMsg.style.display = totalVisible === 0 ? '' : 'none';
            }
          }

          filterInput.addEventListener('input', doFilter);

          if (clearBtn) {
            clearBtn.addEventListener('click', function() {
              filterInput.value = '';
              doFilter();
              filterInput.focus();
            });
          }

          filterInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
              filterInput.value = '';
              doFilter();
              filterInput.blur();
            } else if (e.key === 'ArrowDown') {
              e.preventDefault();
              const firstLink = navMenu.querySelector('li.nav-item:not([style*="display: none"]):not(#sidebar-menu-empty) a.nav-link');
              if (firstLink) firstLink.focus();
            } else if (e.key === 'Enter') {
              e.preventDefault();
              const visibleLinks = Array.from(navMenu.querySelectorAll('li.nav-item:not([style*="display: none"]):not(#sidebar-menu-empty) a.nav-link'))
                .filter(function(a) {
                  const href = (a.getAttribute('href') || '').trim();
                  return href !== '' && href !== '#';
                });
              if (visibleLinks.length === 1) {
                visibleLinks[0].click();
              } else if (visibleLinks.length > 1) {
                visibleLinks[0].focus();
              }
            }
          });
        });
      })();
    </script>
    <main class="app-main">
