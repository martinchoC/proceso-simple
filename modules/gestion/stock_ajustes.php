<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Ajustes de Stock";
$currentPage = 'stock_ajustes';
$modulo_idx = 2;
$pagina_idx = 77;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-boxes me-2"></i>Ajustes de Stock
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Stock</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ajustes de Stock</li>
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
                                            <div class="col-md-2">
                                                <div id="contenedor-boton-agregar" class="d-inline"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dataTables_length" id="tablaStockAjustes_length"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dataTables_filter" id="tablaStockAjustes_filter"></div>
                                            </div>
                                            <div class="col-md-4 text-end">
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
                                    </div>

                                    <div class="card-body">
                                        <table id="tablaStockAjustes" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="100">Tipo</th>
                                                    <th width="120">Sucursal</th>
                                                    <th width="120">Depósito</th>
                                                    <th width="120">Fecha</th>
                                                    <th width="200">Descripción</th>
                                                    <th width="120">Estado</th>
                                                    <th width="250" class="text-center">Acciones</th>
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

            <!-- Modal principal -->
            <div class="modal fade" id="modalStockAjuste" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-boxes me-2 text-primary"></i>Ajuste de Stock
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formStockAjuste" class="needs-validation" novalidate>
                                <input type="hidden" id="stock_ajuste_id" name="stock_ajuste_id" />
                                
                                <!-- SECCIÓN ENCABEZADO -->
                                <div class="card mb-3 border-primary" style="background-color: #f0f7ff;">
                                    <div class="card-header py-2 bg-primary bg-opacity-10 border-bottom border-primary">
                                        <h6 class="mb-0 text-primary">
                                            <i class="fas fa-file-invoice me-2"></i>Datos del Ajuste
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row mb-2">
                                            <div class="col-md-3 mb-2">
                                                <label for="sucursal_id" class="form-label small mb-1">Sucursal</label>
                                                <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                                    <option value="">Seleccionar sucursal</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione sucursal</div>
                                            </div>
                                            
                                            <div class="col-md-3 mb-2">
                                                <label for="deposito_id" class="form-label small mb-1">Depósito</label>
                                                <select class="form-select form-select-sm" id="deposito_id" name="deposito_id" required>
                                                    <option value="">Seleccionar depósito</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione depósito</div>
                                            </div>
                                            
                                            <div class="col-md-2 mb-2">
                                                <label for="comprobante_tipo_id" class="form-label small mb-1">Tipo</label>
                                                <select class="form-select form-select-sm" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                                    <option value="">Seleccionar</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione el tipo</div>
                                            </div>
                                            
                                            <div class="col-md-2 mb-2">
                                                <label for="fecha" class="form-label small mb-1">Fecha</label>
                                                <input type="datetime-local" class="form-control form-control-sm" id="fecha" name="fecha" required>
                                                <div class="invalid-feedback small">Fecha obligatoria</div>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-12 mb-2">
                                                <label for="descripcion" class="form-label small mb-1">Descripción</label>
                                                <textarea class="form-control form-control-sm" id="descripcion" name="descripcion" rows="1" maxlength="255"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- BOTONES SUPERIORES -->
                                <div class="row mt-2 mb-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                                            <i class="fas fa-save me-1"></i>Guardar Ajuste
                                        </button>
                                    </div>
                                </div>

                                <!-- SECCIÓN DETALLES -->
                                <div class="card border-warning">
                                    <div class="card-header py-2 bg-warning bg-opacity-10 border-bottom border-warning">
                                        <h6 class="mb-0 text-warning">
                                            <i class="fas fa-boxes me-2"></i>Detalle de Productos
                                        </h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <!-- Fila de agregado rápido -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="card card-primary card-outline border-info">
                                                    <div class="card-header py-1 bg-info bg-opacity-10">
                                                        <h6 class="mb-0 small text-info"><i class="fas fa-plus-circle me-2"></i>Agregar Producto</h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-md-3 position-relative">
                                                                <label class="small mb-1">Producto</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                    id="busqueda_producto" 
                                                                    placeholder="Buscar producto..."
                                                                    autocomplete="off">
                                                                <input type="hidden" id="producto_seleccionado_id">
                                                                <div id="resultados_busqueda" class="list-group position-absolute" style="z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%; display: none;"></div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="small mb-1">Stock Sistema</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_stock_sistema" 
                                                                    step="0.000001" value="0.000000" readonly>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="small mb-1">Stock Físico</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_stock_fisico" 
                                                                    step="0.000001" min="0" value="0.000000">
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="small mb-1">Diferencia</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_diferencia" 
                                                                    step="0.000001" value="0.000000" readonly>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="small mb-1">Costo Unitario</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_costo" 
                                                                    step="0.000001" min="0" value="0.000000">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <label class="small mb-1">&nbsp;</label>
                                                                <button type="button" class="btn btn-sm btn-success w-100" id="btnAgregarProducto">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Lista de detalles -->
                                        <div class="row">
                                            <div class="col-12">
                                                <div id="contenedor-detalles" class="table-responsive">
                                                    <div class="detalles-vacio text-center p-4 border rounded bg-light" id="detalles-vacio">
                                                        <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                                                        <p class="mb-0">No hay productos agregados</p>
                                                        <small class="text-muted">Busque y agregue productos al ajuste</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .no-spinner::-webkit-inner-spin-button,
        .no-spinner::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .no-spinner {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .modal-fullscreen {
            max-width: 100%;
            margin: 0;
            height: 100vh;
        }
        .modal-fullscreen .modal-content {
            height: 100vh;
            border-radius: 0;
        }
        .modal-fullscreen .modal-body {
            overflow-y: auto;
        }
        #resultados_busqueda {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background-color: white;
            border-radius: 4px;
        }
        #resultados_busqueda .list-group-item.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        #resultados_busqueda .list-group-item {
            cursor: pointer;
            padding: 8px 12px;
            font-size: 0.85rem;
            border-left: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
        }
        #resultados_busqueda .list-group-item:first-child {
            border-top: 1px solid #dee2e6;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
        }
        #resultados_busqueda .list-group-item:last-child {
            border-bottom: 1px solid #dee2e6;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
        }
        #resultados_busqueda .list-group-item:hover:not(.active) {
            background-color: #f8f9fa;
        }
        .table-sm td, .table-sm th {
            padding: 0.5rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .was-validated .form-control:valid,
        .was-validated .form-select:valid,
        .form-control.is-valid,
        .form-select.is-valid {
            border-color: #28a745 !important;
            background-image: none !important;
            padding-right: 0.75rem !important;
        }
        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid,
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545 !important;
            background-image: none !important;
            padding-right: 0.75rem !important;
        }
        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 80%;
        }
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback,
        .form-select.is-invalid ~ .invalid-feedback {
            display: block;
        }
        .card-header {
            font-weight: 500;
        }
        #contenedor-detalles table {
            font-size: 0.85rem;
        }
        #contenedor-detalles thead {
            background-color: #e9ecef;
        }
        .detalles-vacio {
            color: #6c757d;
        }
        .column-filter {
            width: 100%;
            padding: 0.25rem;
            font-size: 0.8rem;
            border: 1px solid #ced4da;
            border-radius: 0.2rem;
            margin-top: 0.25rem;
        }
        .column-filter:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        #tablaStockAjustes thead th {
            vertical-align: top;
            padding-bottom: 0.5rem;
        }
        .dt-buttons {
            display: none !important;
        }
        #tablaStockAjustes_length,
        #tablaStockAjustes_filter {
            margin: 0;
            padding: 0;
        }
        #tablaStockAjustes_length label,
        #tablaStockAjustes_filter label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            gap: 5px;
        }
        #tablaStockAjustes_length select {
            width: auto;
            display: inline-block;
            margin: 0 5px;
        }
        #tablaStockAjustes_filter input {
            width: 200px;
            margin-left: 5px;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            display: none !important;
        }
        @media (max-width: 768px) {
            .card-header .row > div {
                margin-bottom: 10px;
                text-align: center !important;
            }
            .card-header .col-md-3,
            .card-header .col-md-2,
            .card-header .col-md-4 {
                width: 100%;
            }
            #tablaStockAjustes_filter label,
            #tablaStockAjustes_length label {
                justify-content: center;
            }
            #tablaStockAjustes_filter input {
                width: 150px;
            }
            .btn-group {
                justify-content: center;
            }
        }
    </style>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="stock_ajustes.js?v=<?= filemtime(__DIR__.'/stock_ajustes.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>