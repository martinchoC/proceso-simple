<?php
require_once __DIR__ . '/../../db.php';

// Mismo criterio que proveedor_precios.php / ventas_pedidos.php: sin default
// hardcodeado de pagina_id, viene por GET desde el menú/acceso directo.
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 0;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Productos - Proveedores";
$currentPage = 'productos_proveedores';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    #tablaProductosProveedores, #tablaProductosProveedores td {
        font-size: 0.82rem;
    }
    #tablaProductosProveedores th {
        font-size: 0.72rem;
    }
    .chip-vinculo {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background-color: #eef2f7;
        border: 1px solid #dde3ea;
        border-radius: 1rem;
        padding: 0.15rem 0.5rem;
        margin: 0.1rem;
        font-size: 0.78rem;
        white-space: nowrap;
    }
    .chip-vinculo .chip-codigo {
        font-weight: 600;
    }
    .chip-vinculo .chip-accion {
        cursor: pointer;
        color: #6c757d;
        font-size: 0.72rem;
    }
    .chip-vinculo .chip-accion:hover {
        color: #212529;
    }
    .chip-vinculo .chip-accion.chip-quitar:hover {
        color: #dc3545;
    }
    .compatibilidad-texto {
        display: block;
        font-size: 0.72rem;
        color: #6c757d;
        margin-top: 0.15rem;
    }
    .btn-vincular {
        padding: 0.15rem 0.4rem;
        font-size: 0.72rem;
        line-height: 1.2;
    }

    /* ===== Dashboard (ver skill dataviz: tokens por rol, un hue secuencial) ===== */
    .viz-root {
        --surface-1: #fcfcfb;
        --text-primary: #0b0b0b;
        --text-secondary: #52514e;
        --text-muted: #898781;
        --gridline: #e1e0d9;
        --series-1: #2a78d6;
        --series-1-track: #e1e0d9;
    }
    @media (prefers-color-scheme: dark) {
        :root:where(:not([data-theme="light"])) .viz-root {
            --surface-1: #1a1a19;
            --text-primary: #ffffff;
            --text-secondary: #c3c2b7;
            --text-muted: #898781;
            --gridline: #2c2c2a;
            --series-1: #3987e5;
            --series-1-track: #2c2c2a;
        }
    }
    :root[data-theme="dark"] .viz-root {
        --surface-1: #1a1a19;
        --text-primary: #ffffff;
        --text-secondary: #c3c2b7;
        --text-muted: #898781;
        --gridline: #2c2c2a;
        --series-1: #3987e5;
        --series-1-track: #2c2c2a;
    }
    .viz-bar-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.32rem 0;
        border-bottom: 1px solid var(--gridline);
    }
    .viz-bar-row:last-child { border-bottom: none; }
    .viz-bar-label {
        flex: 0 0 40%;
        font-size: 0.78rem;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .viz-bar-track {
        flex: 1 1 auto;
        background: var(--series-1-track);
        border-radius: 4px;
        height: 14px;
        overflow: hidden;
    }
    .viz-bar-fill {
        background: var(--series-1);
        height: 100%;
        border-radius: 4px;
        transition: filter 0.15s ease;
        min-width: 4px;
    }
    .viz-bar-row:hover .viz-bar-fill {
        filter: brightness(1.15);
    }
    .viz-bar-value {
        flex: 0 0 auto;
        min-width: 44px;
        text-align: right;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .stat-tile-label {
        font-size: 0.72rem;
        color: #6c757d;
    }
    .stat-tile-value {
        font-size: 1.6rem;
        font-weight: 600;
    }
</style>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tachometer-alt me-2"></i>Productos - Proveedores
                        <small class="text-muted" style="font-size: 0.85rem;">(Correlación de productos con códigos de proveedor)</small>
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Compras</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Productos - Proveedores</li>
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

                        <ul class="nav nav-tabs mb-2" id="tabsProductosProveedores" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-listado-btn" data-bs-toggle="tab" data-bs-target="#tab-listado" type="button" role="tab" aria-controls="tab-listado" aria-selected="true">
                                    <i class="fas fa-list me-1"></i>Listado
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-dashboard-btn" data-bs-toggle="tab" data-bs-target="#tab-dashboard" type="button" role="tab" aria-controls="tab-dashboard" aria-selected="false">
                                    <i class="fas fa-chart-simple me-1"></i>Dashboard
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="tabsProductosProveedoresContent">

                            <!-- ==================================================== -->
                            <!-- PESTAÑA: Listado                                      -->
                            <!-- ==================================================== -->
                            <div class="tab-pane fade show active" id="tab-listado" role="tabpanel" aria-labelledby="tab-listado-btn">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                            <input type="text" class="form-control" id="filtroTexto" placeholder="Buscar por código, nombre o código de proveedor...">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <select id="filtroProveedor" class="form-select form-select-sm">
                                                            <option value="">Todos los proveedores</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <select id="filtroVinculo" class="form-select form-select-sm">
                                                            <option value="todos">Todos los productos</option>
                                                            <option value="con">Con proveedor vinculado</option>
                                                            <option value="sin">Sin proveedor vinculado</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="btn-group w-100" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcel" title="Exportar a Excel">
                                                                <i class="fas fa-file-excel"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDF" title="Exportar a PDF">
                                                                <i class="fas fa-file-pdf"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Filtros de compatibilidad — mismo criterio que proveedor_precios.php -->
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
                                            </div>

                                            <div class="card-body">
                                                <input type="hidden" id="empresa_id_hidden" value="<?= $empresa_id ?>" />
                                                <input type="hidden" id="pagina_id_hidden" value="<?= $pagina_id ?>" />

                                                <table id="tablaProductosProveedores" class="table table-striped table-bordered table-sm" style="width:100%">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Código</th>
                                                            <th>Nombre</th>
                                                            <th style="width:70px;">Cant.</th>
                                                            <th>Proveedores</th>
                                                            <th style="width:50px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ==================================================== -->
                            <!-- PESTAÑA: Dashboard                                    -->
                            <!-- ==================================================== -->
                            <div class="tab-pane fade" id="tab-dashboard" role="tabpanel" aria-labelledby="tab-dashboard-btn">

                                <div class="row g-2 mb-3" id="dashboardKpis">
                                    <div class="col-12 text-muted small py-3 text-center">
                                        <i class="fas fa-spinner fa-spin me-1"></i>Cargando dashboard…
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="card h-100">
                                            <div class="card-header py-2">
                                                <strong>Top 10 proveedores</strong>
                                                <span class="text-muted small">— por cantidad de productos vinculados</span>
                                            </div>
                                            <div class="card-body viz-root" id="chartTopProveedores">
                                                <p class="text-muted small mb-0 py-3 text-center">Cargando…</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="card h-100">
                                            <div class="card-header py-2">
                                                <strong>Correlación por marca</strong>
                                                <span class="text-muted small">— top 10 marcas por catálogo</span>
                                            </div>
                                            <div class="card-body viz-root" id="chartCorrelacionMarca">
                                                <p class="text-muted small mb-0 py-3 text-center">Cargando…</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header py-2">
                                                <strong>Proveedores sin ningún producto vinculado</strong>
                                                <span class="badge bg-secondary" id="badgeSinVinculos">…</span>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height:320px;">
                                                    <table class="table table-sm table-striped mb-0" id="tablaProveedoresSinVinculos">
                                                        <thead class="table-light">
                                                            <tr><th>Proveedor</th><th>CUIT</th></tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL: Agregar vínculo producto-proveedor                    -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalVinculoProveedor" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary text-white py-2">
                            <h5 class="modal-title" id="modalVinculoProveedorTitulo">Vincular proveedor</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <input type="hidden" id="vinculo_producto_id">
                            <input type="hidden" id="vinculo_producto_proveedor_id">

                            <div class="mb-2">
                                <label class="form-label mb-0">Producto</label>
                                <div class="fw-bold" id="vinculoProductoNombre"></div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Proveedor</label>
                                <select id="vinculo_entidad_id" class="form-select"></select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Código de proveedor</label>
                                <input type="text" id="vinculo_codigo_proveedor" class="form-control" maxlength="50">
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" id="btnGuardarVinculo">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>

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
            <script src="productos_proveedores.js?v=<?= filemtime(__DIR__ . '/productos_proveedores.js') ?>"></script>
        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
