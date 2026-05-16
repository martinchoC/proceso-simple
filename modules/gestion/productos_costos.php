<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Productos Costos";
$currentPage = 'productos_costos';
$modudo_idx = 2;
$pagina_id = 81;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-dollar-sign me-2"></i>Productos Costos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Productos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Costos</li>
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
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="float-end">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    id="btnRecargar" title="Recargar tabla">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Exportar datos">
                                                    <i class="fas fa-file-export"></i> Exportar
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i
                                                                class="fas fa-file-excel text-success"></i> Excel</a>
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i
                                                                class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i
                                                                class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i
                                                                class="fas fa-print text-secondary"></i> Imprimir</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <table id="tablaProductosCostos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="100">Código Producto</th>
                                                    <th width="250">Producto</th>
                                                    <th width="120">Costo Actual</th>
                                                    <th width="100">Moneda</th>
                                                    <th width="100">Origen</th>
                                                    <th width="100">Comprobante</th>
                                                    <th width="100">F. Actualización</th>
                                                    <th width="100">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
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
            <div class="modal fade" id="modalProductoCostos" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-dollar-sign me-2 text-primary"></i>Producto Costo
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formProductoCostos" class="needs-validation" novalidate>
                                <input type="hidden" id="producto_costo_id" name="producto_costo_id" />
                                
                                <div class="card mb-3 border-primary">
                                    <div class="card-header py-2 bg-primary bg-opacity-10">
                                        <h6 class="mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>Datos del Costo</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row mb-2">
                                            <div class="col-md-8">
                                                <label class="form-label small mb-1">Producto *</label>
                                                <select class="form-select form-select-sm" id="producto_id" name="producto_id" required>
                                                    <option value="">Seleccionar producto</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione un producto</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Moneda</label>
                                                <select class="form-select form-select-sm" id="moneda_id" name="moneda_id">
                                                    <option value="">Seleccionar moneda</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Costo Actual *</label>
                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                       id="costo_actual" name="costo_actual" step="0.000001" 
                                                       min="0" required>
                                                <div class="invalid-feedback small">Costo actual obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Origen</label>
                                                <select class="form-select form-select-sm" id="producto_costo_origen_id" name="producto_costo_origen_id">
                                                    <option value="">Seleccionar origen</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Comprobante</label>
                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                       id="comprobante_id" name="comprobante_id" 
                                                       placeholder="ID del comprobante">
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">F. Actualización *</label>
                                                <input type="date" class="form-control form-control-sm" id="f_actualizacion" required>
                                                <div class="invalid-feedback small">Fecha de actualización obligatoria</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Observaciones</label>
                                                <textarea class="form-control form-control-sm" id="observaciones" 
                                                          name="observaciones" rows="2" 
                                                          placeholder="Observaciones..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="row mt-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                                            <i class="fas fa-save me-1"></i>Guardar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para ver historial -->
            <div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="historialLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-info bg-opacity-10">
                            <h5 class="modal-title" id="historialLabel">
                                <i class="fas fa-history me-2 text-info"></i>Historial de Costos
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="mb-3 alert alert-info">
                                <strong>Producto:</strong> <span id="historial_producto_nombre"></span>
                            </div>
                            
                            <!-- Filtros -->
                            <div class="card mb-3 border-secondary">
                                <div class="card-header py-1 bg-secondary bg-opacity-10">
                                    <h6 class="mb-0 small"><i class="fas fa-filter me-1"></i>Filtros</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <label class="form-label small mb-1">Fecha Desde</label>
                                            <input type="date" class="form-control form-control-sm" id="historial_fecha_desde">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small mb-1">Fecha Hasta</label>
                                            <input type="date" class="form-control form-control-sm" id="historial_fecha_hasta">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-primary w-100" id="btnFiltrarHistorial">
                                                <i class="fas fa-search me-1"></i>Filtrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" id="tablaHistorial">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="60">ID</th>
                                            <th width="120">Costo Anterior</th>
                                            <th width="120">Costo Nuevo</th>
                                            <th width="150">Origen</th>
                                            <th width="80">Comprobante</th>
                                            <th width="100">Fecha</th>
                                            <th>Observaciones</th>
                                            <th width="150">F. Creación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="8" class="text-center">Cargando historial...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginador -->
                            <div class="row mt-3" id="historial-paginador">
                                <div class="col-md-6">
                                    <small class="text-muted" id="historial-info"></small>
                                </div>
                                <div class="col-md-6">
                                    <nav>
                                        <ul class="pagination pagination-sm justify-content-end" id="historial-pagination">
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal-dialog {
            max-width: 800px;
            width: 95%;
            margin: 1.75rem auto;
        }

        .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .modal-fullscreen .modal-dialog {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .modal-fullscreen .modal-body {
            max-height: calc(100vh - 120px) !important;
        }

        .no-spinner::-webkit-inner-spin-button,
        .no-spinner::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .no-spinner {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .was-validated .form-control:valid,
        .was-validated .form-select:valid {
            border-color: #28a745 !important;
        }

        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: none;
        }

        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback {
            display: block;
        }
    </style>

    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="productos_costos.js?v=<?= filemtime(__DIR__.'/productos_costos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>