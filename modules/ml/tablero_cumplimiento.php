<?php
/**
 * Vista de Tablero de Cumplimiento y Drilldown Ejecutivo (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/tablero_cumplimiento.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Tablero de Gobierno y Cumplimiento Legal";
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
                <div class="col-sm-7">
                    <h3 class="mb-0">
                        <i class="fas fa-sitemap me-2 text-primary"></i>Tablero de Gobierno y Cumplimiento
                    </h3>
                    <small class="text-muted">Supervisión ejecutiva consolidada con navegación multinivel (Global ➔ Región ➔ País ➔ Empresa ➔ Instalaciones)</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ejecución y Seguimiento</li>
                        <li class="breadcrumb-item active" aria-current="page">Tablero Ejecutivo</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- ============================================================ -->
            <!-- BARRA DE NAVEGACIÓN Y DRILLDOWN INTERACTIVO                   -->
            <!-- ============================================================ -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2 px-3">
                    <div class="row align-items-center g-2">
                        <!-- Selector de Nivel / Pestañas de Alcance -->
                        <div class="col-12 col-lg-7">
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Nivel de Supervisión:</span>
                            <div class="btn-group btn-group-sm shadow-sm" role="group" id="btnGroupNiveles">
                                <button type="button" class="btn btn-outline-primary btn-drill-nivel active" data-nivel="global">
                                    <i class="fas fa-globe me-1"></i>1. Global Grupo
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-drill-nivel" data-nivel="region">
                                    <i class="fas fa-globe-americas me-1"></i>2. Regiones
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-drill-nivel" data-nivel="pais">
                                    <i class="fas fa-flag me-1"></i>3. Países
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-drill-nivel" data-nivel="empresa">
                                    <i class="fas fa-building me-1"></i>4. Empresas / Plantas
                                </button>
                            </div>
                        </div>

                        <!-- Breadcrumb Dinámico y Botón Refrescar -->
                        <div class="col-12 col-lg-5 text-lg-end text-start pt-1">
                            <div class="d-inline-flex align-items-center">
                                <span class="badge bg-light text-dark border me-2 py-2 px-3 small font-monospace" id="breadcrumbDrilldown">
                                    <i class="fas fa-layer-group text-primary me-1"></i>Grupo Corporativo Casalucho
                                </span>
                                <button id="btnRecargarDrill" class="btn btn-sm btn-outline-secondary" title="Actualizar datos">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FILA 1: KPIs EJECUTIVOS DE IMPACTO INMEDIATO                  -->
            <!-- ============================================================ -->
            <div class="row g-3 mb-3">
                <!-- KPI 1: Tareas Vencidas (CRÍTICO) -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-danger border-4 h-100 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="text-danger small text-uppercase fw-bold">
                                        <i class="fas fa-exclamation-circle me-1"></i>Tareas Vencidas
                                    </span>
                                    <h2 class="mb-0 fw-bold text-danger mt-1" id="kpiVencidas">0</h2>
                                </div>
                                <div class="bg-danger bg-opacity-10 text-danger p-2 rounded-circle">
                                    <i class="fas fa-calendar-times fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-danger-emphasis d-block mt-2 fw-semibold" id="kpiVencidasSub">
                                Requieren intervención inmediata
                            </small>
                        </div>
                    </div>
                </div>

                <!-- KPI 2: Próximas a Vencer (ALERTA) -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-warning border-4 h-100 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="text-warning-emphasis small text-uppercase fw-bold">
                                        <i class="fas fa-clock me-1"></i>Próximas (≤ 7 días)
                                    </span>
                                    <h2 class="mb-0 fw-bold text-warning-emphasis mt-1" id="kpiProximas">0</h2>
                                </div>
                                <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-circle">
                                    <i class="fas fa-hourglass-half fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                En plazo crítico de ejecución
                            </small>
                        </div>
                    </div>
                </div>

                <!-- KPI 3: Tareas en Gestión / Abiertas -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-primary border-4 h-100 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="text-primary small text-uppercase fw-bold">
                                        <i class="fas fa-tasks me-1"></i>En Gestión / En Plazo
                                    </span>
                                    <h2 class="mb-0 fw-bold text-primary mt-1" id="kpiEnTermino">0</h2>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle">
                                    <i class="fas fa-clipboard-list fa-2x"></i>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Total asignadas en curso a término
                            </small>
                        </div>
                    </div>
                </div>

                <!-- KPI 4: Tasa de Cumplimiento Global -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-success border-4 h-100 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="text-success small text-uppercase fw-bold">
                                        <i class="fas fa-shield-alt me-1"></i>Tasa de Cumplimiento
                                    </span>
                                    <h2 class="mb-0 fw-bold text-success mt-1" id="kpiTasa">0%</h2>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success p-2 rounded-circle">
                                    <i class="fas fa-chart-pie fa-2x"></i>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div id="kpiTasaBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted d-block mt-1" id="kpiCiclosSub">
                                0 ciclos de control activos
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FILA 2: GRILLA DRILLDOWN EJECUTIVA                            -->
            <!-- ============================================================ -->
            <div class="card card-outline card-secondary shadow-sm mb-3">
                <div class="card-header py-2 d-flex align-items-center justify-content-between bg-light">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-stream me-2 text-primary"></i>Desglose y Semáforo de Riesgo por <span id="tituloNivelActual" class="text-primary text-uppercase">Región</span>
                    </h5>
                    <div>
                        <span class="badge bg-danger me-1"><i class="fas fa-circle me-1"></i>Crítico (con atrasos)</span>
                        <span class="badge bg-warning text-dark me-1"><i class="fas fa-circle me-1"></i>Alerta (≤7 días)</span>
                        <span class="badge bg-success"><i class="fas fa-circle me-1"></i>Al Día / Conforme</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" id="tablaDrilldown">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th style="width: 280px;">Alcance / Entidad</th>
                                    <th style="width: 110px;" class="text-center">Semáforo</th>
                                    <th style="width: 100px;" class="text-center">Vencidas</th>
                                    <th style="width: 100px;" class="text-center">Próximas</th>
                                    <th style="width: 100px;" class="text-center">En Gestión</th>
                                    <th style="width: 90px;" class="text-center">Cumplidas</th>
                                    <th style="width: 100px;" class="text-center">Total Tareas</th>
                                    <th style="width: 160px;">Cumplimiento</th>
                                    <th style="width: 120px;" class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDrilldown">
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Cargando estructura ejecutiva...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FILA 3: PANELES PARALELOS (TAREAS CRÍTICAS + VTO CICLOS)      -->
            <!-- ============================================================ -->
            <div class="row g-3">
                <!-- Panel Izquierdo: Tareas Críticas y Responsables Asignados -->
                <div class="col-12 col-xl-6">
                    <div class="card card-outline card-danger shadow-sm h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0 fs-6 fw-bold text-danger">
                                <i class="fas fa-bell me-2"></i>Tareas con Vencimiento Crítico y Responsables
                            </h6>
                            <a href="ciclos_tareas.php" class="btn btn-xs btn-outline-danger" title="Ir a bandeja completa">
                                Ver todas <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaTareasCriticas">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Tarea Operativa</th>
                                            <th>Instalación</th>
                                            <th>Responsable (R)</th>
                                            <th>Vencimiento</th>
                                            <th class="text-center">Urgencia</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyTareasCriticas">
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">Sin tareas críticas en el alcance seleccionado.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Derecho: Próximos Vencimientos de Ciclos y Obligaciones -->
                <div class="col-12 col-xl-6">
                    <div class="card card-outline card-info shadow-sm h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0 fs-6 fw-bold text-info">
                                <i class="fas fa-calendar-alt me-2"></i>Vencimiento de Ciclos y Obligaciones Legales
                            </h6>
                            <a href="ciclos.php" class="btn btn-xs btn-outline-info" title="Ir a ciclos">
                                Ver ciclos <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaCiclosActivos">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Obligación / Norma</th>
                                            <th>Entidad Sujeta</th>
                                            <th>Fecha Límite</th>
                                            <th>Plazo Restante</th>
                                            <th class="text-center">Estado Tareas</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCiclosActivos">
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">Sin ciclos activos cargados.</td>
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
</main>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
<script src="tablero_cumplimiento.js?v=<?= time() ?>"></script>
</body>
</html>
