<?php
/**
 * Vista de Tablero de Cumplimiento (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/tablero_cumplimiento.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Tablero de Cumplimiento";
$currentPage = 'tablero_cumplimiento';
$modudo_idx = 3;
$pagina_idx = 355; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line me-2 text-success"></i>Tablero de Cumplimiento
                    </h3>
                    <small class="text-muted">Indicadores consolidados, métricas por criticidad y cierres periódicos auditables (C34)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ejecución y Seguimiento</li>
                        <li class="breadcrumb-item active" aria-current="page">Tablero de Cumplimiento</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Fila 1: KPIs Principales del Mes en Curso -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-outline card-success shadow-sm h-100">
                        <div class="card-body p-3">
                            <span class="text-muted small text-uppercase fw-semibold">Tasa de Cumplimiento Global</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1 mb-2">
                                <h2 class="mb-0 fw-bold text-success" id="kpiTasaCumplimiento">0%</h2>
                                <span class="badge bg-success-subtle text-success border border-success" id="badgePonderado">Pond: 0%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div id="barCumplimiento" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted d-block mt-2" id="kpiPeriodoMes">Mes actual</small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-outline card-primary shadow-sm h-100">
                        <div class="card-body p-3">
                            <span class="text-muted small text-uppercase fw-semibold">Cumplidos a Tiempo vs Plazo</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1 mb-2">
                                <h2 class="mb-0 fw-bold text-primary">
                                    <span id="kpiATiempo">0</span> <small class="fs-6 text-muted font-monospace">/ <span id="kpiFueraPlazo">0</span></small>
                                </h2>
                                <span class="fs-3 text-primary opacity-50"><i class="fas fa-check-circle"></i></span>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-clock text-info me-1"></i>A tiempo / <i class="fas fa-exclamation text-warning me-1"></i>Fuera de plazo
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-outline card-danger shadow-sm h-100">
                        <div class="card-body p-3">
                            <span class="text-muted small text-uppercase fw-semibold">Incumplimientos</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1 mb-2">
                                <h2 class="mb-0 fw-bold text-danger" id="kpiIncumplidos">0</h2>
                                <span class="fs-3 text-danger opacity-50"><i class="fas fa-times-circle"></i></span>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Ciclos vencidos sin cumplimiento
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-outline card-warning shadow-sm h-100">
                        <div class="card-body p-3">
                            <span class="text-muted small text-uppercase fw-semibold">Abiertos / En Gestión</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1 mb-2">
                                <h2 class="mb-0 fw-bold text-warning" id="kpiAbiertos">0</h2>
                                <span class="fs-3 text-warning opacity-50"><i class="fas fa-hourglass-half"></i></span>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Ciclos vigentes pendientes de cierre
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila 2: Desglose por Criticidad y por Entidad -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <div class="card card-outline card-secondary shadow-sm h-100">
                        <div class="card-header py-2 d-flex align-items-center justify-content-between">
                            <h6 class="card-title mb-0 fs-6 fw-bold">
                                <i class="fas fa-layer-group me-1 text-primary"></i>Cumplimiento por Criticidad
                            </h6>
                            <small class="text-muted">Ponderación legal</small>
                        </div>
                        <div class="card-body p-3" id="contenedorCriticidades">
                            <!-- Barras dinámicas -->
                            <p class="text-muted small text-center my-3">Cargando métricas...</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card card-outline card-secondary shadow-sm h-100">
                        <div class="card-header py-2 d-flex align-items-center justify-content-between">
                            <h6 class="card-title mb-0 fs-6 fw-bold">
                                <i class="fas fa-building me-1 text-info"></i>Cumplimiento por Entidad Sujeta a Control
                            </h6>
                            <small class="text-muted">Establecimientos / Sedes</small>
                        </div>
                        <div class="card-body p-3" id="contenedorEntidades">
                            <!-- Barras dinámicas -->
                            <p class="text-muted small text-center my-3">Cargando métricas...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila 3: Consolidar y Cerrar Período -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-calculator me-1 text-primary"></i>Consolidación de Cierre de Período (Caso C34)
                    </h6>
                </div>
                <div class="card-body py-2">
                    <form id="formCalcularCierre" class="row g-2 align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted">Período Desde <span class="text-danger">*</span></label>
                            <input type="date" id="cierreDesde" name="periodo_desde" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 fw-bold text-muted">Período Hasta <span class="text-danger">*</span></label>
                            <input type="date" id="cierreHasta" name="periodo_hasta" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 col-sm-12 text-md-end pt-2">
                            <button type="submit" class="btn btn-sm btn-primary" id="btnCalcularCierre">
                                <i class="fas fa-save me-1"></i>Calcular y Grabar Cierre
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="btnRefrescarTablero" title="Actualizar tablero">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Fila 4: Historial de Cierres de Cumplimiento -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-history me-1 text-secondary"></i>Historial de Cierres Auditables Consolidados
                    </h6>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaCierres" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Período Desde</th>
                                    <th>Período Hasta</th>
                                    <th>Ciclos Totales</th>
                                    <th>A Tiempo</th>
                                    <th>Fuera Plazo</th>
                                    <th>Incumplidos</th>
                                    <th>Eximidos</th>
                                    <th>Tasa Cumplimiento</th>
                                    <th>Calculado En</th>
                                    <th>Responsable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Cargado vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
<script src="tablero_cumplimiento.js?v=<?= time() ?>"></script>
