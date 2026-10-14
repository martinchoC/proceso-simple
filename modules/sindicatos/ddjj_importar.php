<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Importación F.931 / Planilla";
$currentPage = 'ddjj_importar';
$modudo_idx = 6;
$pagina_idx = 1021;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0">
                    <i class="fas fa-file-import me-2 text-primary"></i>Importación de Nóminas
                </h3>
                <small class="text-muted">Archivo F.931 / SICOSS de ARCA o planilla CSV: genera o actualiza el borrador de la DDJJ del período</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Importación de Nóminas</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <button type="button" class="btn btn-primary btn-sm d-none" id="btnImportar">
                    <i class="fas fa-upload me-1"></i> Importar archivo
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargar" title="Recargar">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaImportaciones" class="table table-hover table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>N°</th>
                                <th>Fecha</th>
                                <th>Empleador</th>
                                <th>Período</th>
                                <th>Formato</th>
                                <th>Archivo</th>
                                <th class="text-center">Aceptadas</th>
                                <th class="text-center">Rechazadas</th>
                                <th>DDJJ</th>
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

<!-- Modal Importar -->
<div class="modal fade" id="modalImportar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-import me-2 text-primary"></i>Importar nómina</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formImportar" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Empleador <span class="text-danger">*</span></label>
                            <select class="form-select" name="empleador_id" id="empleador_id" required>
                                <option value="">(Seleccionar)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Período <span class="text-danger">*</span></label>
                            <input type="month" class="form-control" id="periodo_mes" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Formato <span class="text-danger">*</span></label>
                            <select class="form-select" name="formato" id="formato" required>
                                <option value="F931_SICOSS">F.931 / SICOSS (ARCA, .txt)</option>
                                <option value="EXCEL_CSV">Planilla CSV</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Categoría por defecto</label>
                            <select class="form-select" name="categoria_id" id="categoria_id">
                                <option value="">(La primera del convenio)</option>
                            </select>
                            <small class="text-muted">El F.931 no informa la categoría del convenio: se asigna esta y se corrige en el borrador.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Archivo <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="archivo" id="archivo" accept=".txt,.csv" required>
                            <small class="text-muted" id="ayudaFormato">Exportación del F.931 desde el aplicativo de ARCA (ancho fijo).</small>
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        Si ya hay un borrador de DDJJ para ese empleador y período, se reemplaza su nómina. Si el período ya está presentado, se genera una rectificativa en borrador.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4" id="btnConfirmarImportar">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="detalleTitulo">Importación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCuerpo"></div>
            <div class="modal-footer">
                <a class="btn btn-outline-primary d-none" id="detalleIrDdjj" href="ddjj.php">Ir a la DDJJ</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="ddjj_importar.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
