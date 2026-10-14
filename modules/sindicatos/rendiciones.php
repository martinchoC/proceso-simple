<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Rendiciones y Conciliación";
$currentPage = 'rendiciones';
$modudo_idx = 6;
$pagina_idx = 1031;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Rendiciones y Cobranzas
                    </h3>
                    <small class="text-muted">Procesamiento de archivos de recaudación bancaria, conciliación automática y generación de cobros e imputaciones</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Rendiciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Tabla de Rendiciones -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <div id="contenedor-boton-agregar" class="d-inline">
                                <button type="button" class="btn btn-primary btn-sm" id="btnSubirRendicion">
                                    <i class="fas fa-upload me-1"></i> Cargar Archivo de Rendición
                                </button>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargarRendiciones" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="tablaRendiciones" class="table table-hover table-striped align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Canal / Banco</th>
                                        <th>Fecha Rendición</th>
                                        <th>Archivo</th>
                                        <th class="text-center">Líneas</th>
                                        <th class="text-end">Importe Total</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
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
<!-- Modal: Cargar Archivo de Rendición -->
<div class="modal fade" id="modalSubirRendicion" tabindex="-1" aria-labelledby="modalSubirRendicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0" id="modalSubirRendicionLabel">
                    <i class="fas fa-upload me-2"></i>Cargar Archivo de Rendición
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSubirRendicion" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="subirMedioPagoId" class="form-label fw-bold">Canal / Medio de Pago <span class="text-danger">*</span></label>
                        <select class="form-select" id="subirMedioPagoId" required>
                            <option value="">-- Seleccionar Canal --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subirFechaRendicion" class="form-label fw-bold">Fecha de Rendición <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="subirFechaRendicion" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="subirArchivo" class="form-label fw-bold">Archivo Recibido (TXT / CSV) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="subirArchivo" accept=".txt,.csv,.dat" required>
                        <div class="form-text small">Soporta formato posicional BNA / Red Link o planillas delimitadas por punto y coma.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarSubida">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Procesar Archivo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Rendición y Líneas -->
<div class="modal fade" id="modalDetalleRendicion" tabindex="-1" aria-labelledby="modalDetalleRendicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title mb-0" id="modalDetalleRendicionLabel">
                    <i class="fas fa-list-check me-2"></i>Detalle de Líneas de Rendición
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold" id="detCanalNombre">-</div>
                        <div class="small text-muted">Archivo: <span id="detArchivo">-</span> | Fecha: <span id="detFecha">-</span></div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Importe Total</div>
                        <h4 class="fw-bold text-primary mb-0" id="detImporteTotal">$0,00</h4>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Código / Identificador</th>
                                <th>Fecha Pago</th>
                                <th>Volante Emparejado</th>
                                <th>Empleador</th>
                                <th class="text-end">Importe Rendido</th>
                                <th class="text-center">Resultado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyLineasRendicion"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="btnProcesarCobranzaModal">
                    <i class="fas fa-check-double me-1"></i> Procesar Cobranzas e Imputaciones
                </button>
            </div>
        </div>
    </div>
</div>

<script src="rendiciones.js"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
