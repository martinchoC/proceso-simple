<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 78;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Tipos de Asientos Contables";
$currentPage = 'cont_tipos_asientos';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-book me-2"></i>Tipos de Asientos Contables
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Contabilidad</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Asientos</li>
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
                                                <div class="dataTables_length" id="tablaTiposAsientos_length"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dataTables_filter" id="tablaTiposAsientos_filter"></div>
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
                                        <table id="tablaTiposAsientos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="60">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="200">Tipo de Asiento</th>
                                                    <th width="300">Descripción</th>
                                                    <th width="100">Origen</th>
                                                    <th width="120">Módulo Origen</th>
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
            <div class="modal fade" id="modalTipoAsiento" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-book me-2 text-primary"></i>Tipo de Asiento Contable
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formTipoAsiento" class="needs-validation" novalidate>
                                <input type="hidden" id="cont_tipo_asiento_id" name="cont_tipo_asiento_id" />
                                
                                <!-- SECCIÓN DATOS PRINCIPALES -->
                                <div class="card mb-3 border-primary" style="background-color: #f0f7ff;">
                                    <div class="card-header py-2 bg-primary bg-opacity-10 border-bottom border-primary">
                                        <h6 class="mb-0 text-primary">
                                            <i class="fas fa-info-circle me-2"></i>Datos del Tipo de Asiento
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2">
                                                <label for="codigo" class="form-label small mb-1">Código <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="codigo" name="codigo" required maxlength="20">
                                                <div class="invalid-feedback small">Ingrese un código</div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-2">
                                                <label for="cont_tipo_asiento" class="form-label small mb-1">Tipo de Asiento <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="cont_tipo_asiento" name="cont_tipo_asiento" required maxlength="100">
                                                <div class="invalid-feedback small">Ingrese el tipo de asiento</div>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-12 mb-2">
                                                <label for="descripcion" class="form-label small mb-1">Descripción</label>
                                                <textarea class="form-control form-control-sm" id="descripcion" name="descripcion" rows="2" maxlength="255"></textarea>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-4 mb-2">
                                                <label for="origen" class="form-label small mb-1">Origen</label>
                                                <select class="form-select form-select-sm" id="origen" name="origen">
                                                    <option value="manual">Manual</option>
                                                    <option value="automatico">Automático</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-4 mb-2">
                                                <label for="modulo_origen" class="form-label small mb-1">Módulo Origen</label>
                                                <input type="text" class="form-control form-control-sm" id="modulo_origen" name="modulo_origen" maxlength="50" placeholder="Ej: Ventas, Compras, etc.">
                                            </div>
                                            
                                            <div class="col-md-4 mb-2">
                                                <label for="estado_select" class="form-label small mb-1">Estado</label>
                                                <select class="form-select form-select-sm" id="estado_select" name="estado_select">
                                                    <option value="activo">Activo</option>
                                                    <option value="inactivo">Inactivo</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- BOTONES -->
                                <div class="row mt-2 mb-3">
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
        #tablaTiposAsientos thead th {
            vertical-align: top;
            padding-bottom: 0.5rem;
        }
        .dt-buttons {
            display: none !important;
        }
        #tablaTiposAsientos_length,
        #tablaTiposAsientos_filter {
            margin: 0;
            padding: 0;
        }
        #tablaTiposAsientos_length label,
        #tablaTiposAsientos_filter label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            gap: 5px;
        }
        #tablaTiposAsientos_length select {
            width: auto;
            display: inline-block;
            margin: 0 5px;
        }
        #tablaTiposAsientos_filter input {
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
            #tablaTiposAsientos_filter label,
            #tablaTiposAsientos_length label {
                justify-content: center;
            }
            #tablaTiposAsientos_filter input {
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
    <script src="cont_tipos_asientos.js?v=<?= filemtime(__DIR__.'/cont_tipos_asientos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>