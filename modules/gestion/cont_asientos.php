<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 54;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Asientos Contables";
$currentPage = 'cont_asientos';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-book me-2"></i>Asientos Contables
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Contabilidad</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Asientos Contables</li>
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

                                    <div class="card-body p-0">
                                        <table id="tablaContAsientos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="120">N° Asiento</th>
                                                    <th width="100">Fecha</th>
                                                    <th width="150">Descripción</th>
                                                    <th width="100">Moneda</th>
                                                    <th width="120">Total Debe</th>
                                                    <th width="120">Total Haber</th>
                                                    <th width="100">Estado</th>
                                                    <th width="150" class="text-center">Acciones</th>
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

            <!-- Modal de Asiento Contable con TABS internas -->
            <div class="modal fade" id="modalContAsiento" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-book me-2 text-primary"></i>Asiento Contable
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-0">
                            <div class="card mb-0 border-0">
                                <div class="card-header py-2 bg-primary bg-opacity-10">
                                    <ul class="nav nav-tabs card-header-tabs" id="asientoTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" 
                                                data-bs-target="#datosContent" type="button" role="tab">
                                                <i class="fas fa-info-circle me-1"></i>Datos
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="detalles-tab" data-bs-toggle="tab" 
                                                data-bs-target="#detallesContent" type="button" role="tab">
                                                <i class="fas fa-list me-1"></i>Detalles (Debe/Haber)
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body p-3 tab-content">
                                    <!-- TAB DATOS -->
                                    <div class="tab-pane fade show active" id="datosContent" role="tabpanel">
                                        <form id="formContAsiento" class="needs-validation" novalidate>
                                            <input type="hidden" id="cont_asiento_id" name="cont_asiento_id" />
                                            
                                           <div class="row mb-2">
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">N° Asiento</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" 
                                                        id="numero_asiento" name="numero_asiento" readonly 
                                                        style="background-color: #e9ecef !important; font-weight: bold;"
                                                        placeholder="Se generará automáticamente">
                                                    <div class="form-text small text-muted">Se genera automáticamente al guardar</div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Fecha *</label>
                                                    <input type="date" class="form-control form-control-sm" 
                                                        id="f_asiento" name="f_asiento" required 
                                                        value="<?php echo date('Y-m-d'); ?>">
                                                    <div class="invalid-feedback small">Fecha obligatoria</div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Tipo Asiento *</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" 
                                                        id="cont_tipo_asiento_text" readonly 
                                                        style="background-color: #e9ecef !important;"
                                                        value="Asiento Manual">
                                                    <input type="hidden" id="cont_tipo_asiento_id" name="cont_tipo_asiento_id" value="1">
                                                </div>
                                                <div class="col-md-3">
                                                    <!-- Campo estado oculto, se maneja automáticamente -->
                                                    <input type="hidden" id="estado" name="estado" value="borrador">
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-12">
                                                    <label class="form-label small mb-1">Descripción</label>
                                                    <textarea class="form-control form-control-sm" id="descripcion" 
                                                              name="descripcion" rows="2" maxlength="65535" 
                                                              placeholder="Descripción del asiento contable"></textarea>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Sucursal *</label>
                                                    <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                                        <option value="">Seleccionar sucursal</option>
                                                    </select>
                                                    
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Depósito *</label>
                                                    <select class="form-select form-select-sm" id="deposito_id" name="deposito_id" required>
                                                        <option value="">Seleccionar depósito</option>
                                                    </select>
                                                    
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Comprobante</label>
                                                    <select class="form-select form-select-sm" id="comprobante_id" name="comprobante_id">
                                                        <option value="">Sin comprobante</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Entidad</label>
                                                    <select class="form-select form-select-sm" id="entidad_id" name="entidad_id">
                                                        <option value="">Seleccionar entidad</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Moneda *</label>
                                                    <select class="form-select form-select-sm" id="moneda_id" name="moneda_id" required>
                                                        <option value="">Seleccionar moneda</option>
                                                    </select>
                                                    
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Tipo Cambio</label>
                                                    <input type="number" class="form-control form-control-sm" 
                                                           id="tipo_cambio" name="tipo_cambio" step="0.000001" value="1.000000">
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <div class="card bg-light">
                                                        <div class="card-body py-2">
                                                            <div class="row">
                                                                <div class="col-6 text-end">
                                                                    <strong>Total Debe:</strong>
                                                                </div>
                                                                <div class="col-6 text-end">
                                                                    <span id="total_debe_mostrar" class="fw-bold text-success">0.00</span>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-6 text-end">
                                                                    <strong>Total Haber:</strong>
                                                                </div>
                                                                <div class="col-6 text-end">
                                                                    <span id="total_haber_mostrar" class="fw-bold text-danger">0.00</span>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-6 text-end">
                                                                    <strong>Diferencia:</strong>
                                                                </div>
                                                                <div class="col-6 text-end">
                                                                    <span id="diferencia_mostrar" class="fw-bold">0.00</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-12 text-end">
                                                    <button type="button" class="btn btn-secondary btn-sm px-4 me-2" data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-1"></i>Cancelar
                                                    </button>
                                                    <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarAsiento">
                                                        <i class="fas fa-save me-1"></i>Guardar
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- TAB DETALLES -->
                                    <div class="tab-pane fade" id="detallesContent" role="tabpanel">
                                        <div class="mb-3">
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <small class="text-muted">Líneas del asiento (Debe = Haber)</small>
                                                </div>
                                                <div class="col-md-6 text-end">
                                                    <button type="button" class="btn btn-sm btn-success" id="btnNuevoDetalle">
                                                        <i class="fas fa-plus me-1"></i>Agregar Línea
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="tablaContAsientosDetalles" class="table table-striped table-bordered table-sm" style="width:100%">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="50">ID</th>
                                                        <th width="250">Cuenta</th>
                                                        <th width="120">Debe (Local)</th>
                                                        <th width="120">Haber (Local)</th>
                                                        <th width="150">Descripción</th>
                                                        <th width="80" class="text-center">Tipo</th>
                                                        <th width="100" class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <th colspan="2" class="text-end">Totales:</th>
                                                        <th class="text-end" id="total_debe_foot">0.00</th>
                                                        <th class="text-end" id="total_haber_foot">0.00</th>
                                                        <th colspan="3"></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

           <!-- Modal para editar detalle -->
        <div class="modal fade" id="modalContAsientoDetalle" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md">
                <div class="modal-content">
                    <div class="modal-header py-2 bg-info bg-opacity-10">
                        <h5 class="modal-title" id="detalleModalLabel">
                            <i class="fas fa-list me-2 text-info"></i>Línea de Asiento
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="formContAsientoDetalle" class="needs-validation" novalidate>
                            <input type="hidden" id="detalle_cuenta_id" name="detalle_cuenta_id">
                            <input type="hidden" id="detalle_cont_asiento_id" name="cont_asiento_id">
                            
                            <div class="row mb-2">
                                <div class="col-md-12">
                                    <label class="form-label small mb-1">Cuenta Contable *</label>
                                    <select class="form-select form-select-sm" id="cuenta_id" name="cuenta_id" required style="width: 100%;">
                                        <option value="">Seleccionar cuenta</option>
                                    </select>
                                    <div class="invalid-feedback small">Cuenta obligatoria</div>
                                    <small class="text-muted">Escriba para buscar por código o nombre</small>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Debe (Importe Local)</label>
                                    <input type="number" class="form-control form-control-sm" 
                                        id="importe_local_debe" name="importe_local_debe" step="0.01" value="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Haber (Importe Local)</label>
                                    <input type="number" class="form-control form-control-sm" 
                                        id="importe_local_haber" name="importe_local_haber" step="0.01" value="0.00">
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-12">
                                    <label class="form-label small mb-1">Descripción</label>
                                    <textarea class="form-control form-control-sm" id="detalle_descripcion" 
                                            name="descripcion" rows="2" placeholder="Descripción de la línea"></textarea>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12 text-center">
                                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Cancelar
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarDetalle">
                                        <i class="fas fa-save me-1"></i>Guardar Línea
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
        .modal-dialog {
            max-width: 900px;
            width: 95%;
            margin: 1.75rem auto;
        }

        .modal-xl {
            max-width: 1200px;
        }

        .modal-fullscreen .modal-dialog {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .modal-fullscreen .modal-body {
            max-height: calc(100vh - 120px) !important;
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

        .nav-tabs .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

    <!-- Agregar CSS de Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Agregar JS de Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>
    
    <script>
        const empresa_id = <?php echo $empresa_id; ?>;
        const pagina_id = <?php echo $pagina_id; ?>;
        const modulo_id = <?php echo $modulo_id; ?>;
    </script>
    <script src="cont_asientos.js?v=<?= filemtime(__DIR__.'/cont_asientos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>