<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 54;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Mayores Contables";
$currentPage = 'cont__mayores';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-book me-2"></i>Mayores Contables
                    </h3>
                    <small class="text-muted">Consulta de movimientos por cuenta contable</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Contabilidad</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Mayores Contables</li>
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
                        <!-- Filtros -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card card-primary card-outline">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            <i class="fas fa-filter me-1"></i>Filtros de Búsqueda
                                        </h5>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <form id="formFiltros" class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label small">Fecha Desde</label>
                                                <input type="date" class="form-control form-control-sm" id="fecha_desde" name="fecha_desde">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">Fecha Hasta</label>
                                                <input type="date" class="form-control form-control-sm" id="fecha_hasta" name="fecha_hasta">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">Cuenta Contable</label>
                                                <select class="form-select form-select-sm select2" id="cuenta_id" name="cuenta_id">
                                                    <option value="">Todas las cuentas</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" class="btn btn-primary btn-sm w-100" id="btnConsultar">
                                                    <i class="fas fa-search me-1"></i>Consultar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Saldos por Cuenta -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            <i class="fas fa-chart-line me-1"></i>Saldos por Cuenta
                                        </h5>
                                        <div class="float-end">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-file-export"></i> Exportar
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i class="fas fa-file-excel text-success"></i> Excel</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i class="fas fa-print text-secondary"></i> Imprimir</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table id="tablaMayores" class="table table-striped table-bordered table-hover" style="width:100%">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="80">Código</th>
                                                        <th width="250">Cuenta Contable</th>
                                                        <th width="120">Naturaleza</th>
                                                        <th width="150">Saldo Inicial</th>
                                                        <th width="150">Debe</th>
                                                        <th width="150">Haber</th>
                                                        <th width="150">Saldo Final</th>
                                                        <th width="100" class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                                <tfoot class="table-secondary">
                                                    <tr>
                                                        <th colspan="3" class="text-end">TOTALES:</th>
                                                        <th class="text-end" id="total_saldo_inicial">0.00</th>
                                                        <th class="text-end" id="total_debe">0.00</th>
                                                        <th class="text-end" id="total_haber">0.00</th>
                                                        <th class="text-end" id="total_saldo_final">0.00</th>
                                                        <th></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para ver detalle del Mayor -->
            <div class="modal fade" id="modalDetalleMayor" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-info bg-opacity-10">
                            <h5 class="modal-title" id="modalDetalleLabel">
                                <i class="fas fa-list-alt me-2 text-info"></i>Detalle del Mayor
                                <span id="cuentaSeleccionada" class="ms-2 small text-muted"></span>
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
                                <div class="card-header py-2 bg-light">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">Movimientos de la cuenta</small>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button type="button" class="btn btn-sm btn-success" id="btnExportarDetalle">
                                                <i class="fas fa-file-excel me-1"></i>Exportar Detalle
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table id="tablaDetalleMayor" class="table table-striped table-bordered table-sm" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="100">Fecha</th>
                                                    <th width="100">Asiento N°</th>
                                                    <th width="120">Comprobante</th>
                                                    <th width="200">Descripción</th>
                                                    <th width="120">Debe</th>
                                                    <th width="120">Haber</th>
                                                    <th width="120">Saldo</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot class="table-secondary">
                                                <tr>
                                                    <th colspan="4" class="text-end">TOTALES:</th>
                                                    <th class="text-end" id="detalle_total_debe">0.00</th>
                                                    <th class="text-end" id="detalle_total_haber">0.00</th>
                                                    <th class="text-end" id="detalle_saldo_final">0.00</th>
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
    </div>

    <style>
        .modal-fullscreen .modal-dialog {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .modal-fullscreen .modal-body {
            max-height: calc(100vh - 120px) !important;
        }
        .select2-container--default .select2-selection--single {
            height: 31px;
            padding: 2px 0;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
        }
    </style>

    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2-bootstrap-5.min.css" rel="stylesheet" />

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const empresa_id = <?php echo $empresa_id; ?>;
        const pagina_id = <?php echo $pagina_id; ?>;
        const modulo_id = <?php echo $modulo_id; ?>;
    </script>
    <script src="cont__mayores.js?v=<?= filemtime(__DIR__.'/cont__mayores.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>