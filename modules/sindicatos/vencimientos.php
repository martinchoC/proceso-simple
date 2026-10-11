<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Cronograma de Vencimientos por CUIT";
$currentPage = 'vencimientos';
$modudo_idx = 6;
$pagina_idx = 1004;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>Vencimientos por CUIT
                    </h3>
                    <small class="text-muted">Cronograma mensual de vencimientos según el último dígito del CUIT de cada empleador</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Vencimientos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <div id="contenedor-boton-agregar" class="d-inline"></div>
                            <button type="button" class="btn btn-outline-success btn-sm ms-2" id="btnGenerarPeriodo">
                                <i class="fas fa-magic me-1"></i> Generar Período Completo
                            </button>

                            <div class="float-end d-flex align-items-center">
                                <label class="me-2 small fw-semibold text-muted">Filtrar Período:</label>
                                <input type="number" class="form-control form-control-sm me-2" id="filtroPeriodo" placeholder="AAAAMM" style="width: 120px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecargar">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tablaVencimientos" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Período</th>
                                            <th class="text-center">Terminación CUIT</th>
                                            <th>Tipo</th>
                                            <th>Fecha de Vencimiento</th>
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
    </div>

    <!-- Modal Individual -->
    <div class="modal fade" id="modalVencimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalVencimientoTitle">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>Vencimiento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formVencimiento">
                    <div class="modal-body">
                        <input type="hidden" name="vencimiento_id" id="vencimiento_id" value="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Período (AAAAMM) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="periodo" name="periodo" required placeholder="ej: 202610">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dígito CUIT (0 a 9) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="terminacion_cuit" name="terminacion_cuit" min="0" max="9" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tipo Vencimiento</label>
                                <select class="form-select" id="tipo_vencimiento" name="tipo_vencimiento">
                                    <option value="DDJJ">Presentación DDJJ</option>
                                    <option value="APORTE">Aporte Bancario</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fecha de Vencimiento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="f_vto" name="f_vto" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarVto">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Generación Masiva -->
    <div class="modal fade" id="modalGenerarPeriodo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-magic me-2 text-success"></i>Generar Cronograma de Período
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formGenerarPeriodo">
                    <div class="modal-body">
                        <p class="text-muted small">Genera automáticamente los vencimientos para los dígitos 0 al 9 con días hábiles escalonados.</p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Período a Generar (AAAAMM) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="gen_periodo" name="periodo" required placeholder="ej: 202610">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fecha Base (Dígitos 0 y 1) <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="gen_f_base" name="f_base" required>
                            <small class="text-muted">Los dígitos sucesivos se desplazarán automáticamente en días hábiles siguientes.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success px-4" id="btnSubmitGenerar">Generar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="vencimientos.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
