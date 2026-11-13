<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

// Obtener parámetros de la URL
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 0;
$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 5;

$pageTitle = "Gestión de Pedidos";
$currentPage = 'ventas_pedidos_gestion';
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Pedidos de clientes','fas fa-dolly', 'Preparación y entrega — pedidos pendientes y en curso', ['Ventas']); ?>

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
                                                <div class="dataTables_filter" id="tablaPedidosGestion_filter"></div>
                                            </div>
                                            <div class="col-md-7 text-end">
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
                                        <div class="row align-items-center mt-2">
                                            <div class="col-md-3">
                                                <input type="text" class="form-control form-control-sm" id="filtro_cliente" placeholder="Filtrar por cliente...">
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select form-select-sm" id="filtro_sucursal">
                                                    <option value="">Todas las sucursales</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select form-select-sm" id="filtro_punto_venta">
                                                    <option value="">Todos los PV</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 text-muted small">
                                                Pendientes de preparar, en preparación y con entrega parcial.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <table id="tablaPedidosGestion" class="table table-striped table-bordered table-sm table-slim" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="55" class="text-center text-nowrap">Número</th>
                                                    <th width="200">Cliente</th>
                                                    <th width="140">Sucursal</th>
                                                    <th width="100">Emisión</th>
                                                    <th width="120">Estado</th>
                                                    <th width="100" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MODAL DE PICKING: se abre al elegir un pedido de la grilla    -->
    <!-- ============================================================ -->
    <div class="modal fade" id="modalPicking" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <!-- Datos del pedido (solo lectura) al lado del título, como el encabezado de entidad de clientes -->
                    <h5 class="modal-title ui-titulo-entidad">
                        <span><i class="fas fa-dolly me-2"></i>Preparar pedido</span>
                        <span class="ui-entidad-datos">
                            <span class="ui-entidad-nombre" id="picking_cliente_fantasia">-</span>
                            <span class="ui-entidad-chip" style="font-variant-numeric: tabular-nums;">N° <span id="picking_comprobante_nro">-</span></span>
                            <span class="ui-entidad-chip"><i class="fas fa-store"></i><span id="picking_sucursal">-</span></span>
                            <span class="ui-entidad-chip"><i class="far fa-calendar"></i><span id="picking_f_emision">-</span></span>
                            <span class="ui-entidad-chip ui-chip-claro" id="picking_estado_badge">-</span>
                        </span>
                    </h5>
                    <button type="button" class="btn btn-sm btn-light ms-auto me-2" id="btnImprimirPedidoPicking" title="Imprimir pedido"><i class="fas fa-print me-1"></i>Imprimir</button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    <!-- Cabecera del picking: el remito que se está armando (lo que hay que completar).
                         Los datos del pedido de referencia van en el encabezado del modal. -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6 col-lg-5">
                            <div class="card card-outline card-primary mb-0 h-100">
                                <div class="card-header py-1 bg-primary bg-opacity-10">
                                    <h6 class="mb-0 small">Datos del Remito</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <select class="form-select form-select-sm" id="picking_punto_venta_id" aria-label="Punto de venta"></select>
                                        </div>
                                        <div class="col-4">
                                            <input type="text" class="form-control form-control-sm" id="picking_remito_tipo_texto" placeholder="Tipo comprobante" readonly>
                                            <input type="hidden" id="picking_remito_comprobante_tipo_id">
                                        </div>
                                        <div class="col-4">
                                            <input type="date" class="form-control form-control-sm" id="picking_remito_f_emision" aria-label="Fecha de emisión">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Solapas: Preparar (picking) / Remitos (visibilidad de lo ya enviado) -->
                    <ul class="nav nav-tabs" id="tabsPicking" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-picking-preparar" data-bs-toggle="tab" href="#tabpane-picking-preparar" role="tab">
                                <i class="fas fa-dolly me-1"></i>Preparar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-picking-detalle" data-bs-toggle="tab" href="#tabpane-picking-detalle" role="tab">
                                <i class="fas fa-list me-1"></i>Detalle Del Pedido
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tabpane-picking-preparar" role="tabpanel">

                            <!-- Pendientes de este pedido -->
                            <div class="card card-outline card-primary mb-3">
                                <div class="card-header py-1 bg-primary bg-opacity-10">
                                    <h6 class="mb-0 small"><i class="fas fa-box me-2"></i>Pendientes De Este Pedido</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div id="contenedor-picking-pendientes-pedido"></div>
                                    <div class="border-top mt-2 pt-2">
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <label class="form-label fw-bold small">Observaciones</label>
                                                <textarea class="form-control form-control-sm" id="picking_remito_observaciones" rows="1"></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-end">
                                            <button type="button" class="btn btn-sm btn-success" id="btnGuardarRemitoPicking">
                                                <i class="fas fa-save me-1"></i>Guardar Remito
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" id="btnConfirmarRemitoPicking">
                                                <i class="fas fa-check-double me-1"></i>Confirmar Remito
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pendientes de otros pedidos del mismo cliente -->
                            <div class="card card-outline card-info mb-3" id="card-picking-pendientes-otros">
                                <div class="card-header py-1 bg-info bg-opacity-10">
                                    <h6 class="mb-0 small"><i class="fas fa-layer-group me-2"></i>Pendientes De Otros Pedidos Del Cliente</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div id="contenedor-picking-pendientes-otros"></div>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="tabpane-picking-detalle" role="tabpanel">
                            <div id="contenedor-picking-detalle-pedido">
                                <div class="text-muted small text-center p-3">No hay detalle disponible.</div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #contenedor-picking-pendientes-pedido, #contenedor-picking-pendientes-otros, #contenedor-picking-remitos-pedido {
            font-size: 0.85rem;
        }
        #contenedor-picking-pendientes-pedido table, #contenedor-picking-pendientes-otros table, #contenedor-picking-remitos-pedido table {
            border-radius: 8px;
            overflow: hidden;
        }
        #contenedor-picking-pendientes-pedido thead th, #contenedor-picking-pendientes-otros thead th, #contenedor-picking-remitos-pedido thead th {
            background: #f1f3f5 !important;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
            padding: 0.5rem 0.75rem;
        }
        #contenedor-picking-pendientes-pedido tbody td, #contenedor-picking-pendientes-otros tbody td, #contenedor-picking-remitos-pedido tbody td {
            padding: 0.15rem 0.75rem;
            vertical-align: middle;
        }
        /* Alto de fila estandarizado (pero compacto) en las tablas de productos
           pendientes: sin el mínimo, una fila con varias ubicaciones (que crecen en
           columna) queda mucho más alta que las demás y desalinea el resto de la tabla.
           No aplica a la fila de detalle expandible (.collapse), que necesita su alto propio. */
        #contenedor-picking-pendientes-pedido tbody tr:not(.collapse), #contenedor-picking-pendientes-otros tbody tr:not(.collapse) {
            height: 32px;
        }
        #contenedor-picking-pendientes-pedido .ubicaciones-pendiente-container,
        #contenedor-picking-pendientes-otros .ubicaciones-pendiente-container {
            max-height: 26px;
            overflow-y: auto;
        }
        /* Mismo stepper +/- que ya usa ventas_pedidos.js — se copia el estilo para que se vea igual */
        .cantidad-stepper {
            display: inline-flex;
            align-items: center;
            border: 1px solid #ced4da;
            border-radius: 6px;
            overflow: hidden;
        }
        .cantidad-stepper .btn-stepper {
            border: none;
            background: #f1f3f5;
            width: 26px;
            height: 26px;
            line-height: 1;
            font-weight: 700;
            cursor: pointer;
        }
        .cantidad-stepper .btn-stepper:hover { background: #dee2e6; }
        .cantidad-stepper .btn-stepper:active { background: #ced4da; }
        .cantidad-stepper-input {
            border: none;
            width: 44px;
            text-align: center;
            font-size: 0.85rem;
            padding: 0.2rem 0;
        }
        .cantidad-stepper-input:focus { outline: none; background: #fff8e1; }
        .input-cantidad-picking::-webkit-outer-spin-button,
        .input-cantidad-picking::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .input-cantidad-picking { -moz-appearance: textfield; appearance: textfield; width: 56px; }
        .badge-ubicacion { font-size: 0.7rem; padding: 0.2rem 0.4rem; margin-bottom: 0.1rem; border-radius: 0.25rem; }
        .badge-seccion { background-color: #dc3545 !important; color: #ffffff !important; }
        .badge-estanteria { background-color: #ffc107 !important; color: #000000 !important; }
        .badge-estante { background-color: #198754 !important; color: #ffffff !important; }
        .badge-posicion { background-color: #6c757d !important; color: #ffffff !important; }
        .ubicaciones-pendiente-container { display: flex; flex-direction: column; gap: 2px; }

        .confirmacion-remito-popup {
            border-radius: 18px;
            padding: 0 0 1.25rem;
            overflow: hidden;
        }
        .confirmacion-remito { color: #243044; text-align: center; }
        .confirmacion-remito-icon {
            width: 68px;
            height: 68px;
            margin: 1.5rem auto 0.75rem;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #d1fae5;
            color: #047857;
            font-size: 1.8rem;
        }
        .confirmacion-remito-eyebrow {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .confirmacion-remito h3 { margin: 0.25rem 0; font-weight: 700; }
        .confirmacion-remito-numero { color: #475569; margin-bottom: 1rem; }
        .confirmacion-remito-detalle { margin: 0 1.25rem; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
        .confirmacion-remito-tabla-wrap { max-height: 270px; overflow-y: auto; }
        .confirmacion-remito-detalle table { margin-bottom: 0; }
        .confirmacion-remito-detalle thead th { background: #f8fafc; color: #64748b; font-size: 0.75rem; }
        .confirmacion-remito-boton { border-radius: 8px !important; padding: 0.55rem 1.6rem !important; }

        /* Pedidos en estado_registro_id=5 (pendientes de preparar): pastel para diferenciar sin saturar */
        #tablaPedidosGestion tr.fila-pendiente-preparar td {
            background-color: #eef1f5 !important;
        }
        #tablaPedidosGestion tr.fila-confirmado-pendiente-revision td {
            font-weight: 700;
        }
    </style>

    <link rel="stylesheet" type="text/css"
        href="<?= asset_local('css/datatables-buttons-2.4.1/buttons.dataTables.min.css') ?>">

    <script src="<?= asset_local('js/datatables-buttons-2.4.1/dataTables.buttons.min.js') ?>"></script>
    <script src="<?= asset_local('js/jszip-3.10.1/jszip.min.js') ?>"></script>
    <script src="<?= asset_local('js/pdfmake-0.1.53/pdfmake.min.js') ?>"></script>
    <script src="<?= asset_local('js/pdfmake-0.1.53/vfs_fonts.js') ?>"></script>
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.html5.min.js') ?>"></script>
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        const PAGINA_ID = <?= $pagina_id ?>;
        const EMPRESA_ID = <?= $empresa_id ?>;
        const MODULO_ID = <?= $modulo_id ?>;
    </script>
    <script src="ventas_pedidos_gestion.js?v=<?= filemtime(__DIR__.'/ventas_pedidos_gestion.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
