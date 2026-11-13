<?php
/**
 * Tablero de Gobierno y Cumplimiento Legal (Módulo 3: Matriz Legal)
 * Archivo: modules/ml/index.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Gobierno y Cumplimiento Legal";
$currentPage = 'ml_index';
$modudo_idx = 3;
$pagina_idx = 64;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Consultas rápidas para contadores
$q_terr = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__territorios WHERE tabla_estado_registro_id = 1");
$tot_terr = ($r = mysqli_fetch_assoc($q_terr)) ? (int)$r['total'] : 0;

$q_ent = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__entidades WHERE tabla_estado_registro_id = 1");
$tot_ent = ($r = mysqli_fetch_assoc($q_ent)) ? (int)$r['total'] : 0;

$q_tar = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__ciclos_tareas");
$tot_tar = ($r = mysqli_fetch_assoc($q_tar)) ? (int)$r['total'] : 0;

$qs_modulo = "";
?>

<!-- Leaflet CSS para Mapa Geográfico Real -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<!-- Estilos ClickUp para Matriz Legal -->
<link rel="stylesheet" href="../../assets/css/clickup-ml.css?v=<?= time() ?>">

<style>
/* Estilos ClickUp Dashboard Metrics */
.cu-metric-card {
    background: #ffffff;
    border: 1px solid var(--cu-border);
    border-radius: 10px;
    padding: 16px 18px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    height: 100%;
}
.cu-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
}
.cu-metric-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.cu-metric-icon-urgent {
    background: rgba(220, 38, 38, 0.1);
    color: var(--cu-urgent);
}
.cu-metric-icon-warning {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}
.cu-metric-icon-primary {
    background: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}
.cu-metric-icon-purple {
    background: rgba(123, 104, 238, 0.12);
    color: var(--cu-purple);
}

/* Estilos Mapa Geográfico Real Leaflet */
.map-container-card {
    background: #0f172a;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.15);
}

#mapRealLeaflet {
    height: 420px;
    width: 100%;
    border-radius: 8px;
    z-index: 10;
}

/* Pines cartográficos personalizados con pulso */
.custom-geo-marker {
    position: relative;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.custom-geo-pin {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    border: 2px solid #ffffff;
    cursor: pointer;
    transition: transform 0.2s ease;
    z-index: 2;
}
.custom-geo-pin:hover {
    transform: scale(1.25);
}
.custom-geo-pin.pin-danger { background: #ef4444; }
.custom-geo-pin.pin-warning { background: #f59e0b; }
.custom-geo-pin.pin-success { background: #10b981; }

.geo-pulse-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    animation: marker-pulse-anim 2s infinite ease-out;
    z-index: 1;
}
.geo-pulse-ring.pulse-danger { background: rgba(239, 68, 68, 0.5); }
.geo-pulse-ring.pulse-warning { background: rgba(245, 158, 11, 0.5); }
.geo-pulse-ring.pulse-success { background: rgba(16, 185, 129, 0.5); }

@keyframes marker-pulse-anim {
    0% { transform: scale(0.9); opacity: 0.9; }
    100% { transform: scale(2.4); opacity: 0; }
}

/* Popups Leaflet */
.leaflet-popup-content-wrapper {
    border-radius: 8px !important;
    padding: 0 !important;
    overflow: hidden !important;
    box-shadow: 0 10px 25px -3px rgba(0,0,0,0.3) !important;
}
.leaflet-popup-content {
    margin: 0 !important;
    line-height: 1.4 !important;
}
.leaflet-popup-tip {
    background: #ffffff !important;
}

.country-row-item {
    transition: all 0.15s ease;
    cursor: pointer;
    border-left: 4px solid transparent;
}
.country-row-item:hover, .country-row-item.active {
    background-color: #f8fafc;
}
.country-row-item.active {
    border-left-color: var(--cu-purple);
}

.module-quick-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    text-decoration: none !important;
    color: inherit;
    border-radius: 10px;
    border: 1px solid var(--cu-border);
}
.module-quick-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.06) !important;
    border-color: #cbd5e1;
}
</style>

<main class="app-main">
    <!-- Header Limpio y Práctico -->
    <div class="app-content-header py-2 border-bottom bg-white">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h4 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-shield-alt me-2" style="color: var(--cu-purple);"></i>Gobierno y Cumplimiento Legal
                    </h4>
                </div>
                <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                    <a href="ciclos_tareas.php<?= $qs_modulo ?>" class="btn btn-sm btn-primary me-2 shadow-sm" style="background: var(--cu-purple); border-color: var(--cu-purple);">
                        <i class="fas fa-tasks me-1"></i>Bandeja de Tareas
                    </a>
                    <button id="btnRecargarDrill" class="btn btn-sm btn-outline-secondary" title="Actualizar datos">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content pt-3">
        <div class="container-fluid">

            <!-- ============================================================ -->
            <!-- 1. KPIS CLAVE DE CUMPLIMIENTO (ESTILO CLICKUP DASHBOARD)     -->
            <!-- ============================================================ -->
            <div class="row g-3 mb-3">
                <!-- Vencidas -->
                <div class="col-6 col-xl-3">
                    <div class="cu-metric-card" style="border-left: 4px solid var(--cu-urgent) !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-uppercase fw-bold text-danger">Tareas Vencidas</span>
                                <h3 class="mb-0 fw-bold text-danger mt-1" id="kpiVencidas">0</h3>
                                <small class="text-muted" style="font-size: 0.75rem;">Requieren acción inmediata</small>
                            </div>
                            <div class="cu-metric-icon-wrap cu-metric-icon-urgent">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Próximas -->
                <div class="col-6 col-xl-3">
                    <div class="cu-metric-card" style="border-left: 4px solid var(--cu-high) !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-uppercase fw-bold" style="color: #d97706;">Próximas (≤ 7 días)</span>
                                <h3 class="mb-0 fw-bold mt-1" style="color: #d97706;" id="kpiProximas">0</h3>
                                <small class="text-muted" style="font-size: 0.75rem;">En ventana de riesgo</small>
                            </div>
                            <div class="cu-metric-icon-wrap cu-metric-icon-warning">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- En Término / Listas -->
                <div class="col-6 col-xl-3">
                    <div class="cu-metric-card" style="border-left: 4px solid #0d6efd !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-uppercase fw-bold text-primary">En Plazo / En Curso</span>
                                <h3 class="mb-0 fw-bold text-primary mt-1" id="kpiEnTermino">0</h3>
                                <small class="text-muted" style="font-size: 0.75rem;">Planificadas sin retraso</small>
                            </div>
                            <div class="cu-metric-icon-wrap cu-metric-icon-primary">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- % Cumplimiento -->
                <div class="col-6 col-xl-3">
                    <div class="cu-metric-card" style="border-left: 4px solid var(--cu-purple) !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-uppercase fw-bold" style="color: var(--cu-purple);">% Cumplimiento</span>
                                <h3 class="mb-0 fw-bold mt-1" style="color: var(--cu-purple);" id="kpiTasa">0%</h3>
                            </div>
                            <div class="cu-metric-icon-wrap cu-metric-icon-purple">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                        </div>
                        <div class="cu-group-progress-bar mt-2" style="height: 6px; background: #e2e8f0;">
                            <div id="kpiTasaBar" class="cu-group-progress-fill" style="width: 0%; background: linear-gradient(90deg, var(--cu-purple), #10b981);"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 2. MAPA REGIONAL Y MONITOR POR PAÍS                          -->
            <!-- ============================================================ -->
            <div class="row g-3 mb-3">
                <!-- Mapa Geográfico Real Leaflet (Cono Sur) -->
                <div class="col-12 col-xl-7">
                    <div class="card shadow-sm border-0 h-100 bg-white">
                        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-2 px-3">
                            <div class="d-flex align-items-center">
                                <span class="fw-bold text-dark small me-2">
                                    <i class="fas fa-globe-americas me-2 text-primary"></i>Mapa Territorial de Instalaciones
                                </span>
                                <div class="btn-group btn-group-xs d-none d-sm-inline-flex" role="group">
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-map-zoom active" data-target="arg">🇦🇷 Argentina</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-map-zoom" data-target="rosales">⛽ Puerto Rosales</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-map-zoom" data-target="martinez">🏢 Martínez</button>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <span class="badge bg-danger shadow-sm">Crítico</span>
                                <span class="badge bg-warning text-dark shadow-sm">Alerta</span>
                                <span class="badge bg-success shadow-sm">Al Día</span>
                            </div>
                        </div>

                        <div class="card-body p-2 position-relative">
                            <div id="mapRealLeaflet"></div>
                        </div>
                    </div>
                </div>

                <!-- Monitor por País (Limpio y Funcional) -->
                <div class="col-12 col-xl-5">
                    <div class="card shadow-sm border-0 h-100 bg-white">
                        <div class="card-header bg-white py-2 border-bottom d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark small">
                                <i class="fas fa-flag text-primary me-2"></i>Estado por País
                            </span>
                            <span class="badge bg-light text-muted border">1 Jurisdicción Activa</span>
                        </div>

                        <div class="card-body p-0">
                            <div class="list-group list-group-flush" id="listaPaisesMonitor">
                                <!-- Argentina -->
                                <div class="list-group-item country-row-item p-3 active" data-country="ARG" data-pais-id="2">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-5 me-2">🇦🇷</span>
                                            <div>
                                                <strong class="text-dark">Argentina</strong>
                                                <span class="text-muted small ms-1">(2 instalaciones: Terminal Puerto Rosales y Sede Martínez)</span>
                                            </div>
                                        </div>
                                        <span class="badge bg-warning text-dark">EN CURSO</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small mt-2">
                                        <div class="d-flex gap-3">
                                            <span class="text-muted">0 Vencidas</span>
                                            <span class="text-warning-emphasis fw-bold"><i class="fas fa-clock me-1"></i>1 En Curso</span>
                                            <span class="text-success"><i class="fas fa-check me-1"></i>5 Listas</span>
                                        </div>
                                        <a href="ciclos_tareas.php?pais_id=2" class="btn btn-xs btn-outline-primary px-2">
                                            Ver Tareas <i class="fas fa-chevron-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 3. DETALLE DE INSTALACIONES Y TAREAS CRÍTICAS                 -->
            <!-- ============================================================ -->
            <div class="card shadow-sm border-0 mb-3 bg-white" id="seccionDrilldown">
                <div class="card-header bg-white py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <!-- Pestañas -->
                    <ul class="nav nav-pills card-header-pills" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active py-1 px-3 fw-semibold small" data-bs-toggle="tab" href="#tab-drilldown-tabla" role="tab">
                                <i class="fas fa-building me-1"></i>Instalaciones y Sedes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 fw-semibold small text-danger" data-bs-toggle="tab" href="#tab-tareas-criticas" role="tab">
                                <i class="fas fa-exclamation-triangle me-1"></i>Tareas Críticas
                            </a>
                        </li>
                    </ul>

                    <!-- Selector de Nivel de Vista -->
                    <div class="d-flex align-items-center">
                        <span class="badge bg-light text-dark border me-2 small font-monospace" id="breadcrumbDrilldown">
                            Grupo Casalucho
                        </span>
                        <div class="btn-group btn-group-sm" role="group" id="btnGroupNiveles">
                            <button type="button" class="btn btn-outline-secondary btn-drill-nivel active" data-nivel="global">Global</button>
                            <button type="button" class="btn btn-outline-secondary btn-drill-nivel" data-nivel="pais">Países</button>
                            <button type="button" class="btn btn-outline-secondary btn-drill-nivel" data-nivel="empresa">Instalaciones</button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- Pestaña 1: Tabla de Instalaciones -->
                        <div class="tab-pane fade show active" id="tab-drilldown-tabla" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="tablaDrilldown">
                                    <thead class="table-light text-muted small text-uppercase">
                                        <tr>
                                            <th>Entidad / Alcance</th>
                                            <th class="text-center" style="width: 120px;">Estado</th>
                                            <th class="text-center" style="width: 90px;">Vencidas</th>
                                            <th class="text-center" style="width: 90px;">Próximas</th>
                                            <th class="text-center" style="width: 90px;">Tareas</th>
                                            <th style="width: 160px;">Cumplimiento</th>
                                            <th class="text-center" style="width: 120px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyDrilldown"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña 2: Tareas Críticas -->
                        <div class="tab-pane fade p-3" id="tab-tareas-criticas" role="tabpanel">
                            <div id="contenedorTareasCriticas"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 4. ACCESO DIRECTO A MÓDULOS (COMPACTO Y PRÁCTICO)            -->
            <!-- ============================================================ -->
            <div class="row g-2 mb-4">
                <!-- 1. Planner -->
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="ciclos_tareas.php<?= $qs_modulo ?>" class="card module-quick-card shadow-sm border-0 p-2 bg-white text-center h-100">
                        <div class="text-primary mb-1"><i class="fas fa-tasks fa-lg"></i></div>
                        <strong class="small d-block text-dark">Tareas</strong>
                        <span class="badge bg-primary bg-opacity-10 text-primary small"><?= $tot_tar ?> activas</span>
                    </a>
                </div>

                <!-- 2. Obligaciones -->
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="obligaciones.php<?= $qs_modulo ?>" class="card module-quick-card shadow-sm border-0 p-2 bg-white text-center h-100">
                        <div class="text-success mb-1"><i class="fas fa-clipboard-check fa-lg"></i></div>
                        <strong class="small d-block text-dark">Obligaciones</strong>
                        <span class="text-muted small">Catálogo Legal</span>
                    </a>
                </div>

                <!-- 3. Normas -->
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="normas.php<?= $qs_modulo ?>" class="card module-quick-card shadow-sm border-0 p-2 bg-white text-center h-100">
                        <div class="text-info mb-1"><i class="fas fa-book-open fa-lg"></i></div>
                        <strong class="small d-block text-dark">Normativa</strong>
                        <span class="text-muted small">Leyes y Res.</span>
                    </a>
                </div>

                <!-- 4. Activos -->
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="entidades.php<?= $qs_modulo ?>" class="card module-quick-card shadow-sm border-0 p-2 bg-white text-center h-100">
                        <div class="text-secondary mb-1"><i class="fas fa-boxes fa-lg"></i></div>
                        <strong class="small d-block text-dark">Instalaciones</strong>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary small"><?= $tot_ent ?> activos</span>
                    </a>
                </div>

                <!-- 5. Evidencias -->
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="evidencias.php<?= $qs_modulo ?>" class="card module-quick-card shadow-sm border-0 p-2 bg-white text-center h-100">
                        <div class="text-warning mb-1"><i class="fas fa-shield-alt fa-lg"></i></div>
                        <strong class="small d-block text-dark">Evidencias</strong>
                        <span class="text-muted small">Documentos</span>
                    </a>
                </div>

                <!-- 6. Territorios -->
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="territorios.php<?= $qs_modulo ?>" class="card module-quick-card shadow-sm border-0 p-2 bg-white text-center h-100">
                        <div class="text-dark mb-1"><i class="fas fa-calendar-alt fa-lg"></i></div>
                        <strong class="small d-block text-dark">Territorios</strong>
                        <span class="badge bg-dark bg-opacity-10 text-dark small"><?= $tot_terr ?> países</span>
                    </a>
                </div>
            </div>

        </div>
    </div>
<!-- Leaflet JS para Mapa Cartográfico Real -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="index.js?v=<?= time() ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>