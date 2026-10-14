<?php
/**
 * modules/sindicatos/fiscalizacion_tablero.php
 * Vista del Tablero de Fiscalización (Página 1040).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Tablero de Fiscalización y Mora";
$currentPage = 'fiscalizacion_tablero';
$modudo_idx = 6;
$pagina_idx = 1040;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line me-2 text-danger"></i>Tablero de Fiscalización
                    </h3>
                    <small class="text-muted">Monitoreo de cumplimiento de aportes, matriz de mora y control de omisión de trabajadores</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Fiscalización</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Tarjetas KPI -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Padrón Empleadores</span>
                                    <h3 class="fw-bold text-primary mb-0 mt-1" id="kpiTotalEmp">-</h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                    <i class="fas fa-building fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-sm-6">
                    <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Al Día (Puntual)</span>
                                    <h3 class="fw-bold text-success mb-0 mt-1" id="kpiPuntuales">-</h3>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-sm-6">
                    <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Mora Temprana / Media</span>
                                    <h3 class="fw-bold text-warning mb-0 mt-1" id="kpiMoraMedia">-</h3>
                                </div>
                                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-sm-6">
                    <div class="card shadow-sm border-0 border-start border-4 border-danger h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Mora Grave / Crónica</span>
                                    <h3 class="fw-bold text-danger mb-0 mt-1" id="kpiMoraGrave">-</h3>
                                </div>
                                <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3">
                                    <i class="fas fa-bell fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros y Acciones -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Buscar Razón Social o CUIT</label>
                                    <input type="text" class="form-control form-control-sm" id="filtroBusqueda" placeholder="Ej: Comercio, 30-...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Categoría de Mora</label>
                                    <select class="form-select form-select-sm" id="filtroMora">
                                        <option value="">-- Todas las categorías --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Período Desde</label>
                                    <input type="number" class="form-control form-control-sm" id="filtroPeriodoDesde" placeholder="AAAAMM">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Período Hasta</label>
                                    <input type="number" class="form-control form-control-sm" id="filtroPeriodoHasta" placeholder="AAAAMM">
                                </div>
                                <div class="col-md-2 text-md-end mt-md-4">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnRecalcularMora" title="Recalcular días de mora para todos los empleadores">
                                        <i class="fas fa-calculator me-1"></i> Recalcular Mora
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm ms-1" id="btnFiltrar" title="Aplicar filtros">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matriz de Cumplimiento -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold">
                                <i class="fas fa-th me-2 text-primary"></i>Matriz de Cumplimiento (Empleador × Período)
                            </h5>
                            <span class="badge bg-light text-muted border" id="badgeTotalMatriz">Cargando...</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 600px;">
                                <table class="table table-hover table-bordered align-middle mb-0 text-center" id="tablaMatriz">
                                    <thead class="table-light sticky-top" id="theadMatriz">
                                        <tr>
                                            <th class="text-start" style="min-width: 260px;">Empleador / Razón Social</th>
                                            <th style="min-width: 130px;">Categoría Mora</th>
                                            <!-- Columnas de períodos dinámicas -->
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyMatriz">
                                        <tr>
                                            <td colspan="10" class="py-5 text-muted">
                                                <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando matriz de cumplimiento...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Modal Detección Empleados Omitidos -->
<div class="modal fade" id="modalOmitidos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning bg-opacity-10 text-dark">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-times text-warning me-2"></i>Control de Nómina: Trabajadores Omitidos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Trabajadores declarados en períodos anteriores que no figuran en la presentación actual y no tienen motivo de baja registrado (<code>ddjj_empleados_out</code>).
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaOmitidos">
                        <thead class="table-light">
                            <tr>
                                <th>CUIL</th>
                                <th>Apellido y Nombre</th>
                                <th>Categoría</th>
                                <th class="text-end">Última Remun.</th>
                                <th>Observación</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyOmitidos">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="modules/sindicatos/fiscalizacion_tablero.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
