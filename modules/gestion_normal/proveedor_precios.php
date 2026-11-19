<?php
require_once __DIR__ . '/../../db.php';
$modudo_idx = 5; // gestion_normal

$pagina_idx = 592;
$pagina_id = 592;
$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 5;

$pageTitle = "Precios de Proveedores";
$currentPage = 'proveedor_precios';
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    #tablaProveedorPrecios {
        width: 100% !important;
        font-size: 0.78rem;
    }
    #tablaProveedorPrecios th,
    #tablaProveedorPrecios td {
        padding: 0.22rem 0.35rem;
        vertical-align: middle;
    }
    #tablaProveedorPrecios th {
        font-size: 0.70rem;
        white-space: nowrap;
    }
    #tablaProveedorPrecios td:first-child {
        max-width: 120px;
        white-space: normal;
        word-break: break-word;
    }
    /* Producto se adapta al ancho disponible y parte líneas largas para que entren todas las columnas y botones */
    #tablaProveedorPrecios th.col-producto,
    #tablaProveedorPrecios td.col-producto {
        width: 22%;
        min-width: 140px;
        max-width: 220px;
        white-space: normal;
        word-break: break-word;
    }
    #tablaProveedorPrecios td:not(.col-producto):not(:first-child) {
        white-space: nowrap;
    }
    #tablaImportaciones, #tablaDetalleImportacion { font-size: 0.82rem; }
</style>

<main class="app-main">
    <?php ui_encabezado('Precios de proveedores','fas fa-truck-loading', '', ['Compras']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <select id="filtro_proveedor" class="form-select form-select-sm">
                                                    <option value="">Seleccione un proveedor…</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcel" title="Exportar a Excel">
                                                        <i class="fas fa-file-excel"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDF" title="Exportar a PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportarCSV" title="Exportar a CSV">
                                                        <i class="fas fa-file-csv"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarPrint" title="Imprimir tabla">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Buscador general — mismo estilo que productos.php (versión simple,
                                             sin el sistema de tags/chips por palabra; si hace falta ese nivel
                                             de detalle, se puede sumar después). Busca por código propio,
                                             código del proveedor y nombre del producto. -->
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                    <input type="text" class="form-control" id="filtroCodigo" placeholder="Buscar por código o nombre del producto...">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Filtros de compatibilidad vehicular: gestion_normal no maneja marcas/modelos de vehículo
                                             (productos.php de este módulo tampoco), se dejan ocultos para que el .js no falle. -->
                                        <div class="row align-items-center mt-2 d-none" id="filaFiltrosCompatibilidad">
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-trademark"></i></span>
                                                    <select class="form-select" id="filtroMarca">
                                                        <option value="">Todas las marcas</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-car"></i></span>
                                                    <select class="form-select" id="filtroModelo" disabled>
                                                        <option value="">Todos los modelos</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-cube"></i></span>
                                                    <select class="form-select" id="filtroSubmodelo" disabled>
                                                        <option value="">Todos los submodelos</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Toolbar de acciones — debajo de todos los filtros. Orden:
                                             Importar, Nuevo Precio, Actualizar Costos y Precios, Actualizar
                                             Costos, Actualizar Precios de Venta, % de cambio. flex-wrap para
                                             que en pantallas chicas baje de línea en vez de desbordar. -->
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <div class="d-flex flex-wrap gap-2 justify-content-start">
                                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnImportarExcel" disabled>
                                                        <i class="fas fa-file-excel me-1"></i>Importar
                                                    </button>
                                                    <div id="contenedor-boton-agregar" class="d-inline">
                                                        <button type="button" class="btn btn-sm btn-primary" id="btnAgregarPrecio" disabled>
                                                            <i class="fas fa-plus me-1"></i>Nuevo Precio
                                                        </button>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnArmarListaDesdePrecios" disabled title="Cargar el precio de lista de los productos vinculados a este proveedor que no lo tienen, deducido del precio de venta actual o del costo registrado">
                                                        <i class="fas fa-magic me-1"></i>Armar lista desde precios
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" id="btnActualizarCostosMasivo" disabled title="Actualizar el costo Y la lista de precios de TODOS los productos de este proveedor">
                                                        <i class="fas fa-coins me-1"></i>Actualizar Costos y Precios
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnActualizarSoloCostosMasivo" disabled title="Actualizar SOLO el costo de TODOS los productos de este proveedor, sin tocar listas de precio">
                                                        <i class="fas fa-sync-alt me-1"></i>Actualizar Costos
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnActualizarSoloPreciosMasivo" disabled title="Actualizar SOLO las listas de precio de venta (con el costo ya registrado) de TODOS los productos de este proveedor">
                                                        <i class="fas fa-tags me-1"></i>Actualizar Precios de Venta
                                                    </button>
                                                    <div class="input-group input-group-sm" style="width:auto;">
                                                        <span class="input-group-text">%</span>
                                                        <input type="number" step="0.01" class="form-control" id="porcentajeAjusteCostos" placeholder="Ej: 8 o -5" style="width:90px;">
                                                        <button type="button" class="btn btn-outline-warning" id="btnAplicarPorcentajeCostos" disabled title="Aplica el % al precio de lista del proveedor (positivo sube, negativo baja) de los productos filtrados — no toca el costo del producto">
                                                            <i class="fas fa-percent me-1"></i>% de cambio
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs px-3 pt-2" id="tabsProveedorPrecios" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="tab-listado-btn" data-bs-toggle="tab" data-bs-target="#tab-listado" type="button" role="tab" aria-controls="tab-listado" aria-selected="true">
                                                <i class="fas fa-list me-1"></i>Listado
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-dashboard-btn" data-bs-toggle="tab" data-bs-target="#tab-dashboard" type="button" role="tab" aria-controls="tab-dashboard" aria-selected="false">
                                                <i class="fas fa-chart-pie me-1"></i>Dashboard
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-control-btn" data-bs-toggle="tab" data-bs-target="#tab-control" type="button" role="tab" aria-controls="tab-control" aria-selected="false">
                                                <i class="fas fa-clipboard-check me-1"></i>Control
                                                <span class="badge bg-warning text-dark ms-1 d-none" id="badgeControl"></span>
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-importaciones-btn" data-bs-toggle="tab" data-bs-target="#tab-importaciones" type="button" role="tab" aria-controls="tab-importaciones" aria-selected="false">
                                                <i class="fas fa-history me-1"></i>Importaciones
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="card-body tab-content" id="contenidoTabsProveedorPrecios">
                                        <input type="hidden" id="empresa_id_hidden" name="empresa_id_hidden" value="<?= $empresa_id ?>" />
                                        <input type="hidden" id="pagina_id_hidden" name="pagina_id_hidden" value="<?= $pagina_id ?>" />

                                        <div class="tab-pane fade show active" id="tab-listado" role="tabpanel" aria-labelledby="tab-listado-btn">
                                            <div class="table-responsive">
                                                <table id="tablaProveedorPrecios" class="table table-striped table-bordered table-sm table-slim" style="width:100%">
                                                    <thead class="table-light"></thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="tab-dashboard" role="tabpanel" aria-labelledby="tab-dashboard-btn">
                                            <div id="avisoDashboardSinBusqueda" class="alert alert-info">
                                                Seleccioná un proveedor o aplicá un filtro para ver el dashboard.
                                            </div>
                                            <div id="contenidoDashboard" class="d-none">
                                                <div class="card card-outline card-secondary mb-3">
                                                    <div class="card-header py-2"><h6 class="mb-0">Mayores variaciones de costo</h6></div>
                                                    <div class="card-body p-0" style="max-height:400px; overflow-y:auto;">
                                                        <table class="table table-sm table-striped mb-0" id="dash_tabla_top_costo">
                                                            <thead class="table-light">
                                                                <tr><th>Producto</th><th>Proveedor</th><th class="text-end">Costo actual</th><th class="text-end">Debería ser</th><th class="text-center">Variación</th></tr>
                                                            </thead>
                                                            <tbody></tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <div class="card card-outline card-secondary mb-0">
                                                    <div class="card-header py-2">
                                                        <h6 class="mb-0">Mayores variaciones de lista general</h6>
                                                        <small class="text-muted" id="dash_lista_general_nombre"></small>
                                                    </div>
                                                    <div class="card-body p-0" style="max-height:400px; overflow-y:auto;">
                                                        <table class="table table-sm table-striped mb-0" id="dash_tabla_top_lista_general">
                                                            <thead class="table-light">
                                                                <tr><th>Producto</th><th>Proveedor</th><th class="text-end">Actual</th><th class="text-end">Debería ser</th><th class="text-center">Variación</th></tr>
                                                            </thead>
                                                            <tbody></tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Solapa Control: productos vinculados al proveedor
                                             (gestion__productos_proveedores) sin precio en la lista
                                             del proveedor. No usa los filtros de código/marca/modelo:
                                             siempre muestra el proveedor completo. -->
                                        <div class="tab-pane fade" id="tab-control" role="tabpanel" aria-labelledby="tab-control-btn">
                                            <div id="avisoControlSinProveedor" class="alert alert-info">
                                                Seleccioná un proveedor para ver sus productos vinculados sin precio.
                                            </div>
                                            <div id="contenidoControl" class="d-none">
                                                <p class="text-muted small mb-2">
                                                    Productos vinculados a este proveedor que no tienen precio en su lista.
                                                    Si el proveedor cambió el código de alguno, corregilo en el ABM de
                                                    Productos-Proveedores y volvé a importar la lista.
                                                </p>
                                                <table class="table table-sm table-striped table-bordered" id="tablaControlSinPrecio" style="width:100%">
                                                    <thead class="table-light">
                                                        <tr><th>Cód. Prov.</th><th>Código</th><th>Producto</th><th class="text-center" width="80">Acciones</th></tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Solapa Importaciones: cada lista importada, con el archivo
                                             original y el resultado fila por fila (para controlar errores
                                             del archivo). Del proveedor elegido o, sin proveedor, de todos. -->
                                        <div class="tab-pane fade" id="tab-importaciones" role="tabpanel" aria-labelledby="tab-importaciones-btn">
                                            <table class="table table-sm table-striped table-bordered" id="tablaImportaciones" style="width:100%">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Fecha</th><th>Proveedor</th><th>Archivo</th><th class="ui-detalle">Usuario</th>
                                                        <th class="text-end">Filas</th><th class="text-end">Cargados <?= ui_ayuda('Precios nuevos más precios actualizados') ?></th>
                                                        <th class="text-end">Sin cambios</th><th class="text-end">No vinculados</th>
                                                        <th class="text-end">Con error <?= ui_ayuda('Filas sin código, con precio vacío o en cero, con el código repetido o que no se pudieron grabar') ?></th>
                                                        <th class="text-end">No vinieron <?= ui_ayuda('Productos vinculados al proveedor que no estaban en el archivo') ?></th>
                                                        <th>Estado</th><th class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL: Agregar / Editar precio manual                        -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalPrecioProveedor" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary text-white py-2">
                            <h5 class="modal-title" id="modalPrecioProveedorTitulo">Nuevo Precio</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <input type="hidden" id="producto_id">
                            <input type="hidden" id="producto_proveedor_precio_id">
                            <input type="hidden" id="entidad_id_edicion">

                            <div id="bloqueBuscarProducto">
                                <label>Buscar producto (en todo el catálogo)</label>
                                <input type="text" id="buscadorProductoProveedor" class="form-control mb-2" placeholder="Código o nombre…">
                                <small class="text-muted d-block mb-2">Si el producto todavía no está vinculado a este proveedor, se vincula automáticamente al guardar.</small>
                                <div id="resultadosBuscadorProducto" class="list-group mb-3" style="max-height:220px; overflow-y:auto;"></div>
                            </div>

                            <div id="bloqueProductoSeleccionado" class="alert alert-light border d-none">
                                <strong id="productoSeleccionadoNombre"></strong>
                                <span class="text-muted" id="productoSeleccionadoCodigo"></span>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <label>Precio de lista (sin IVA)</label>
                                    <input type="number" step="0.01" min="0" id="precio_lista" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label>Moneda</label>
                                    <select id="moneda_id" class="form-control"></select>
                                </div>
                                <div class="col-md-4">
                                    <label>Vigente desde</label>
                                    <input type="date" id="f_vigencia_desde" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" id="btnGuardarPrecioProveedor">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL: Historial de precios                                  -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalHistorialPrecio" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-info text-white py-2">
                            <h5 class="modal-title">Historial de precios</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <table class="table table-sm table-striped" id="tablaHistorialPrecio">
                                <thead>
                                <tr>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Precio Lista</th>
                                    <th>Moneda</th>
                                    <th>Origen</th>
                                    <th>Archivo</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="modal-footer py-2">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL: Importar lista de precios (Excel)                     -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalImportarExcel" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-success text-white py-2">
                            <h5 class="modal-title">Importar lista de precios del proveedor</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <p class="text-muted small mb-2">
                                El Excel debe tener columnas <code>codigo_proveedor</code>, <code>descripcion</code>, <code>precio</code>
                                (precio de lista, sin IVA) y <code>f_vigencia_desde</code> (fecha en la que ese precio entra en
                                vigencia — formato <code>AAAA-MM-DD</code> o celda con formato Fecha; si se deja vacía, se usa la fecha de hoy).
                                Los códigos que no matcheen con este proveedor quedan
                                listados aparte para vincular manualmente en el ABM de Productos-Proveedores.
                                Al terminar, también se listan los productos vinculados a este proveedor cuyo
                                código no vino en el archivo (posible cambio de código del proveedor).
                                El archivo y el resultado de cada fila quedan en la solapa <strong>Importaciones</strong>.
                            </p>
                            <input type="file" id="inputArchivoExcel" class="form-control mb-3" accept=".xlsx,.xls">
                            <div class="progress mb-2 d-none" id="progressImportacion">
                                <div class="progress-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <div id="resumenImportacion"></div>
                        </div>
                        <div class="modal-footer py-2">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button class="btn btn-success" id="btnProcesarImportacion">Procesar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL: Detalle de una importación (fila por fila)            -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalDetalleImportacion" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-secondary text-white py-2">
                            <h5 class="modal-title" id="modalDetalleImportacionTitulo">Importación</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <select class="form-select form-select-sm" id="filtroResultadoImportacion" style="width:auto;"></select>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarDetalleImportacion" title="Exportar el detalle a Excel">
                                    <i class="fas fa-file-excel me-1"></i>Excel
                                </button>
                                <a class="btn btn-sm btn-outline-secondary" id="linkArchivoImportacion" href="#" title="Descargar el archivo tal como se cargó">
                                    <i class="fas fa-download me-1"></i>Archivo original
                                </a>
                                <div class="small ms-auto" id="resumenDetalleImportacion"></div>
                            </div>
                            <table class="table table-sm table-striped table-bordered" id="tablaDetalleImportacion" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-end">Fila</th><th>Cód. Prov.</th><th>Descripción del archivo</th><th>Producto</th>
                                        <th class="text-end">Precio</th><th class="text-end">Precio anterior</th><th>Vigente desde</th>
                                        <th>Resultado</th><th>Observación</th><th>Código</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="modal-footer py-2">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL: Armar lista del proveedor desde precios actuales      -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalArmarLista" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary text-white py-2">
                            <h5 class="modal-title">Armar lista del proveedor desde precios actuales</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <p class="text-muted small mb-2">
                                Para los productos vinculados a <strong id="armarListaProveedor"></strong> que todavía no tienen
                                precio en su lista (los de la solapa Control), se deduce el precio de lista desde el precio de
                                venta actual (deshaciendo la regla de la lista elegida) o desde el costo registrado, y se le
                                suma el descuento de compra vigente del proveedor:
                                <code>precio de lista = costo / (1 − descuento)</code>.
                                Quedan cargados con origen <strong>ESTIMADO</strong>; no se tocan costos ni listas de venta.
                                Cuando llegue la lista real del proveedor, importarla los reemplaza.
                            </p>
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1" for="armarListaBase">Calcular desde</label>
                                    <select class="form-select form-select-sm" id="armarListaBase"></select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnArmarListaCalcular">
                                        <i class="fas fa-calculator me-1"></i>Calcular vista previa
                                    </button>
                                </div>
                                <div class="col-md-4 small text-end" id="armarListaResumen"></div>
                            </div>
                            <table class="table table-sm table-striped table-bordered" id="tablaArmarLista" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" width="30"><input type="checkbox" id="armarListaTodos" checked title="Tildar / destildar todos"></th>
                                        <th>Cód. Prov.</th><th>Código</th><th>Producto</th>
                                        <th class="text-end">Base</th><th class="text-end">Costo deducido</th>
                                        <th class="text-end">Precio de lista</th><th>Observación</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="modal-footer py-2">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" id="btnArmarListaGenerar" disabled>
                                <i class="fas fa-check me-1"></i>Cargar en la lista del proveedor
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="<?= asset_local('js/xlsx-0.18.5/xlsx.full.min.js') ?>"></script>
            <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>

            <link rel="stylesheet" type="text/css" href="<?= asset_local('css/datatables-buttons-2.4.1/buttons.dataTables.min.css') ?>">
            <script src="<?= asset_local('js/datatables-buttons-2.4.1/dataTables.buttons.min.js') ?>"></script>
            <script src="<?= asset_local('js/jszip-3.10.1/jszip.min.js') ?>"></script>
            <script src="<?= asset_local('js/pdfmake-0.1.53/pdfmake.min.js') ?>"></script>
            <script src="<?= asset_local('js/pdfmake-0.1.53/vfs_fonts.js') ?>"></script>
            <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.html5.min.js') ?>"></script>
            <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>


            <script>
                const PAGINA_ID = <?= $pagina_id ?>;
                const EMPRESA_ID = <?= $empresa_id ?>;
                const MODULO_ID = <?= $modulo_id ?>;
            </script>
            <script src="proveedor_precios.js?v=<?= filemtime(__DIR__ . '/proveedor_precios.js') ?>"></script>
        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
