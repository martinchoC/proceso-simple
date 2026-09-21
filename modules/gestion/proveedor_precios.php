<?php
require_once __DIR__ . '/../../db.php';

// Obtener parámetros de la URL — mismo criterio que ventas_pedidos.php:
// sin default hardcodeado de pagina_id, viene sí o sí por GET desde el
// menú/acceso directo que arma la URL de esta página.
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 0;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Precios de Proveedores";
$currentPage = 'proveedor_precios';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    #tablaProveedorPrecios, #tablaProveedorPrecios td {
        font-size: 0.82rem;
    }
    #tablaProveedorPrecios th {
        font-size: 0.72rem;
    }
</style>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-truck-loading me-2"></i>Precios de Proveedores
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Compras</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Precios de Proveedores</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

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

                                        <!-- Filtros de compatibilidad — mismo criterio que productos.php -->
                                        <div class="row align-items-center mt-2">
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

                                    <div class="card-body">
                                        <input type="hidden" id="empresa_id_hidden" name="empresa_id_hidden" value="<?= $empresa_id ?>" />
                                        <input type="hidden" id="pagina_id_hidden" name="pagina_id_hidden" value="<?= $pagina_id ?>" />

                                        <table id="tablaProveedorPrecios" class="table table-striped table-bordered table-sm table-slim" style="width:100%">
                                            <thead class="table-light"></thead>
                                            <tbody></tbody>
                                        </table>
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
                <div class="modal-dialog modal-lg">
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

            <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
            <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

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
