<?php
/**
 * Vista de Tareas Operativas - Modelo Microsoft Planner (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ciclos_tareas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Planner de Tareas de Cumplimiento";
$currentPage = 'ciclos_tareas';
$modudo_idx = 3;
$pagina_idx = 343; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
/* ==========================================================================
   ESTILOS MICROSOFT PLANNER PARA GESTIÓN DE TAREAS LEGALES
   ========================================================================== */
.planner-nav-tabs .nav-link {
    font-weight: 600;
    color: #64748b;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 0.6rem 1.2rem;
    transition: all 0.2s ease;
}
.planner-nav-tabs .nav-link:hover {
    color: #0f172a;
    background-color: transparent;
}
.planner-nav-tabs .nav-link.active {
    color: #0d6efd;
    background-color: transparent;
    border-bottom: 3px solid #0d6efd;
}

/* Tablero Kanban */
.planner-board {
    display: flex;
    overflow-x: auto;
    gap: 1.25rem;
    padding-bottom: 1.25rem;
    min-height: 600px;
    align-items: flex-start;
}
.planner-column {
    flex: 0 0 320px;
    width: 320px;
    background-color: #f1f5f9;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 210px);
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.planner-column-header {
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #cbd5e1;
    background-color: #f8fafc;
    border-top-left-radius: 9px;
    border-top-right-radius: 9px;
}
.planner-column-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
}
.planner-column-count {
    background: #e2e8f0;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
}
.planner-column-cards {
    padding: 12px;
    overflow-y: auto;
    flex-grow: 1;
    min-height: 150px;
}

/* Tarjeta de Tarea (Planner Card) */
.planner-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    border-left: 5px solid #94a3b8;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.2s ease;
    cursor: grab;
    position: relative;
}
.planner-card:active {
    cursor: grabbing;
}
.planner-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.planner-card.card-overdue {
    border-left-color: #dc3545;
    background: #fffdfd;
}
.planner-card.card-upcoming {
    border-left-color: #fd7e14;
    background: #fffdfa;
}
.planner-card.card-in-progress {
    border-left-color: #0d6efd;
}
.planner-card.card-review {
    border-left-color: #ffc107;
}
.planner-card.card-completed {
    border-left-color: #198754;
    opacity: 0.85;
}
.planner-card.card-completed .planner-card-title {
    text-decoration: line-through;
    color: #64748b;
}

/* Check circular de estado rápido */
.planner-check-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s ease;
    margin-right: 10px;
    background: #ffffff;
}
.planner-check-circle:hover {
    border-color: #198754;
    background-color: #dcfce7;
    color: #15803d;
}
.planner-check-circle.checked {
    border-color: #198754;
    background-color: #198754;
    color: #ffffff;
}
.planner-card-title {
    font-size: 0.92rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
    margin-bottom: 6px;
}
.planner-tag {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
    margin-bottom: 6px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.planner-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed #f1f5f9;
}
.planner-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
}
.planner-icons {
    color: #64748b;
    font-size: 0.8rem;
    display: flex;
    gap: 8px;
}

/* Estilos para el Hilo de Conversación / Mensajes de Tarea */
.chat-thread-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 250px;
    overflow-y: auto;
    padding: 8px 10px;
    background-color: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
.chat-msg-bubble {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.chat-msg-content {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
    max-width: 90%;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    font-size: 0.85rem;
    line-height: 1.4;
}
.chat-msg-bubble.is-system .chat-msg-content {
    background: #f1f5f9;
    border-style: dashed;
    font-style: italic;
    color: #475569;
}
.chat-msg-meta {
    font-size: 0.72rem;
    color: #64748b;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.chat-avatar-mini {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    color: #fff;
    font-weight: 700;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>
<link rel="stylesheet" href="<?= asset_local('css/clickup-ml.css') ?>?v=<?= time() ?>">

<main class="app-main">
    <div class="cu-view-bar-container">
        <div class="container-fluid py-2">
            <!-- Barra Superior: Título y Acciones -->
            <div class="row align-items-center mb-2">
                <div class="col-sm-6">
                    <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <i class="fas fa-tasks me-2" style="color: var(--cu-purple);"></i>Tareas de Cumplimiento Legal
                    </h4>
                    <small class="text-muted">Bandeja operativa de ejecución, dependencias y evidencias con diseño ClickUp</small>
                </div>
                <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                    <div class="d-inline-flex align-items-center gap-2">
                        <span class="badge border py-2 px-3 font-monospace" id="badgeTotalTareas" style="background: var(--cu-purple-light); color: var(--cu-purple); border-color: var(--cu-purple-border) !important;">
                            Cargando tareas...
                        </span>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary" title="Volver al Tablero de Gobierno">
                            <i class="fas fa-arrow-left me-1"></i>Tablero
                        </a>
                        <button id="btnRecargarPlanner" class="btn btn-sm btn-outline-secondary" title="Actualizar datos">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Selector de Vistas ClickUp (Tabs) -->
            <ul class="nav cu-view-tabs" id="plannerViewTabs" role="tablist">
                <li class="nav-item">
                    <button class="cu-view-tab active" id="tab-grid" data-bs-toggle="tab" data-bs-target="#view-grid" type="button" role="tab">
                        <i class="fas fa-list-ul"></i> Lista <span class="cu-tab-badge" id="badgeCountLista">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="cu-view-tab" id="tab-board" data-bs-toggle="tab" data-bs-target="#view-board" type="button" role="tab">
                        <i class="fas fa-columns"></i> Tablero <span class="cu-tab-badge" id="badgeCountTablero">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="cu-view-tab" id="tab-charts" data-bs-toggle="tab" data-bs-target="#view-charts" type="button" role="tab">
                        <i class="fas fa-chart-pie"></i> Métricas
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="app-content pt-3">
        <div class="container-fluid">

            <!-- ============================================================ -->
            <!-- BARRA DE FILTROS CLICKUP: BUSCADOR + PAÍS + ENTIDAD + ME MODE -->
            <!-- ============================================================ -->
            <div class="cu-filter-card mb-3">
                <div class="card-body py-2 px-3">
                    <div class="row align-items-center g-2">
                        <!-- Buscador instantáneo -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" id="plannerSearch" class="form-control border-start-0 shadow-none" placeholder="Buscar tarea, código, norma...">
                            </div>
                        </div>

                        <!-- Filtro por País -->
                        <div class="col-6 col-md-2">
                            <select id="filtroPais" class="form-select form-select-sm shadow-none">
                                <option value="">-- País --</option>
                            </select>
                        </div>

                        <!-- Filtro por Instalación / Entidad -->
                        <div class="col-6 col-md-2">
                            <select id="filtroEntidad" class="form-select form-select-sm shadow-none">
                                <option value="">-- Instalación --</option>
                            </select>
                        </div>

                        <!-- Dropdown "Agrupar por" -->
                        <div class="col-6 col-md-2">
                            <select id="plannerGroupBy" class="form-select form-select-sm shadow-none">
                                <option value="estado" selected>Agrupar: Estado</option>
                                <option value="responsable">Agrupar: Responsable</option>
                                <option value="entidad">Agrupar: Instalación</option>
                                <option value="vencimiento">Agrupar: Vencimiento</option>
                            </select>
                        </div>

                        <!-- Modo Mis Tareas (Me Mode) -->
                        <div class="col-6 col-md-auto">
                            <button type="button" class="btn btn-sm btn-cu-me-mode w-100" id="btnMeMode" title="Alternar: Ver solo mis tareas asignadas">
                                <i class="fas fa-user-check me-1"></i>Mis Tareas
                            </button>
                        </div>

                        <!-- Píldoras Rápidas de Estado -->
                        <div class="col-12 col-md text-md-end text-start">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="cu-filter-chip chip-urgent btn-filtro-planner" data-filter="vencidas" title="Con retraso">
                                    <i class="fas fa-exclamation-circle text-danger me-1"></i>Vencidas
                                </button>
                                <button type="button" class="cu-filter-chip chip-warning btn-filtro-planner" data-filter="proximas" title="Próximas">
                                    <i class="fas fa-clock text-warning me-1"></i>Próximas
                                </button>
                                <button type="button" class="cu-filter-chip btn-filtro-planner active" data-filter="todas" title="Todas">
                                    Todas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- CONTENIDO DE LAS VISTAS CLICKUP                              -->
            <!-- ============================================================ -->
            <div class="tab-content" id="plannerTabContent">

                <!-- 1. VISTA LISTA AGRUPADA (CLICKUP GROUPED LIST) -->
                <div class="tab-pane fade show active" id="view-grid" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                        <small class="text-muted fw-semibold" id="lblListGroupInfo">
                            <i class="fas fa-layer-group text-primary me-1"></i>Agrupado por Estado de Cumplimiento
                        </small>
                        <div class="d-flex align-items-center gap-2">
                            <div class="btn-group btn-group-xs" role="group">
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnExpandirTodosGrupos" title="Expandir todos los grupos">
                                    <i class="fas fa-angle-double-down me-1"></i>Expandir
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnColapsarTodosGrupos" title="Colapsar todos los grupos">
                                    <i class="fas fa-angle-double-up me-1"></i>Colapsar
                                </button>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnToggleTablaClasica" title="Alternar Grilla Tabular Clásica">
                                <i class="fas fa-table me-1"></i>Tabla Clásica
                            </button>
                        </div>
                    </div>

                    <!-- Contenedor de la Lista Agrupada ClickUp -->
                    <div id="cuListViewContainer" class="cu-list-container">
                        <div class="w-100 text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            Cargando tareas con diseño ClickUp...
                        </div>
                    </div>

                    <!-- Tabla Clásica Oculta por Defecto -->
                    <div id="cuTableViewWrapper" class="d-none mt-3">
                        <div class="card card-outline card-secondary shadow-sm">
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table id="tablaTareas" class="table table-sm table-striped table-hover align-middle w-100">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;">ID</th>
                                                <th>Entidad / Equipo</th>
                                                <th>Obligación</th>
                                                <th>Tarea Operativa</th>
                                                <th style="width: 150px;">Responsable (R)</th>
                                                <th style="width: 140px;">Vencimiento</th>
                                                <th style="width: 90px;" class="text-center">Control</th>
                                                <th style="width: 110px;">Estado</th>
                                                <th style="width: 100px;" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. VISTA TABLERO KANBAN CLICKUP (BOARD) -->
                <div class="tab-pane fade" id="view-board" role="tabpanel">
                    <div class="cu-board-container" id="plannerBoardContainer">
                        <div class="w-100 text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            Cargando tablero Kanban...
                        </div>
                    </div>
                </div>

                <!-- 3. VISTA MÉTRICAS CLICKUP (CHARTS / ANALYTICS) -->
                <div class="tab-pane fade" id="view-charts" role="tabpanel">
                    <div class="row g-3">
                        <!-- Gráfico 1: Estado General -->
                        <div class="col-12 col-md-4">
                            <div class="card shadow-sm border-0 h-100 bg-white">
                                <div class="card-header bg-white py-2 fw-bold border-bottom">
                                    <i class="fas fa-chart-pie me-2" style="color: var(--cu-purple);"></i>Distribución por Estado
                                </div>
                                <div class="card-body p-3 text-center">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="p-2 border rounded" style="background: var(--cu-urgent-bg); border-color: var(--cu-urgent-border) !important;">
                                                <small class="text-danger fw-bold d-block">Con retraso</small>
                                                <h3 class="mb-0 fw-bold text-danger" id="chartCountOverdue">0</h3>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded" style="background: var(--cu-normal-bg); border-color: var(--cu-normal-border) !important;">
                                                <small class="text-primary fw-bold d-block">En curso</small>
                                                <h3 class="mb-0 fw-bold text-primary" id="chartCountProgress">0</h3>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded" style="background: var(--cu-high-bg); border-color: var(--cu-high-border) !important;">
                                                <small class="text-dark fw-bold d-block">Próximas</small>
                                                <h3 class="mb-0 fw-bold text-dark" id="chartCountUpcoming">0</h3>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded" style="background: #ecfdf5; border-color: #a7f3d0 !important;">
                                                <small class="text-success fw-bold d-block">Cumplidas</small>
                                                <h3 class="mb-0 fw-bold text-success" id="chartCountCompleted">0</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 10px; border-radius: 6px;" id="chartProgressBar">
                                        <div class="progress-bar bg-danger" id="barOverdue" style="width: 0%"></div>
                                        <div class="progress-bar bg-warning" id="barUpcoming" style="width: 0%"></div>
                                        <div class="progress-bar bg-primary" id="barProgress" style="width: 0%"></div>
                                        <div class="progress-bar bg-success" id="barCompleted" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 2: Carga por Miembro RACI -->
                        <div class="col-12 col-md-4">
                            <div class="card shadow-sm border-0 h-100 bg-white">
                                <div class="card-header bg-white py-2 fw-bold border-bottom">
                                    <i class="fas fa-users me-2 text-info"></i>Carga por Responsable RACI
                                </div>
                                <div class="card-body p-3" id="chartMembersList" style="max-height: 380px; overflow-y: auto;">
                                    <p class="text-muted text-center my-4">Cargando distribución de miembros...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 3: Tareas por Instalación -->
                        <div class="col-12 col-md-4">
                            <div class="card shadow-sm border-0 h-100 bg-white">
                                <div class="card-header bg-white py-2 fw-bold border-bottom">
                                    <i class="fas fa-cubes me-2 text-secondary"></i>Tareas por Instalación
                                </div>
                                <div class="card-body p-3" id="chartBucketsList" style="max-height: 380px; overflow-y: auto;">
                                    <p class="text-muted text-center my-4">Cargando instalaciones...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<!-- ==========================================================================
     SIDE-OVER DRAWER CLICKUP (Panel Lateral Deslizante para Detalle de Tarea)
     ========================================================================== -->
<div class="offcanvas offcanvas-end cu-task-drawer" tabindex="-1" id="modalDetalleTarea" aria-labelledby="modalDetalleTareaTitulo">
    <div class="cu-drawer-header">
        <div class="cu-drawer-breadcrumbs">
            <i class="fas fa-shield-alt text-primary"></i>
            <span>Matriz Legal</span>
            <i class="fas fa-chevron-right text-muted" style="font-size: 0.65rem;"></i>
            <span id="dtObligacionBadge"></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="dtEstadoBadge"></span>
            <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
    </div>
    <div class="cu-drawer-body">
        <!-- Título Principal de la Tarea -->
        <h4 id="dtTareaTitulo" class="fw-bold text-dark mb-3"></h4>

        <!-- Metadata Grid 2x2 ClickUp -->
        <div class="cu-drawer-meta-grid">
            <div class="cu-meta-item">
                <span class="cu-meta-label"><i class="fas fa-industry me-1"></i>Instalación / Entidad</span>
                <span class="cu-meta-val" id="dtCicloEntidad">--</span>
            </div>
            <div class="cu-meta-item">
                <span class="cu-meta-label"><i class="fas fa-user-check me-1"></i>Responsable Principal (R)</span>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <div id="dtResponsableAvatar" class="cu-avatar" style="width: 22px; height: 22px; font-size: 9px;">?</div>
                    <span class="cu-meta-val" id="dtResponsableNombre">--</span>
                </div>
            </div>
            <div class="cu-meta-item">
                <span class="cu-meta-label"><i class="far fa-calendar-alt me-1"></i>Fecha de Vencimiento</span>
                <span class="cu-meta-val" id="dtVencimiento">--</span>
            </div>
            <div class="cu-meta-item">
                <span class="cu-meta-label"><i class="fas fa-flag me-1"></i>Prioridad / Criticidad</span>
                <span class="cu-meta-val" id="dtPrioridadFlag">--</span>
            </div>
        </div>

        <!-- Predecesoras y Matriz RACI -->
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="p-2 border rounded bg-light h-100">
                    <span class="text-muted fw-bold small text-uppercase d-block mb-1">
                        <i class="fas fa-sitemap me-1 text-primary"></i>Predecesoras
                    </span>
                    <ul id="ulPredecesoras" class="list-group list-group-sm small border-0 bg-transparent">
                        <li class="list-group-item bg-transparent text-muted px-0 py-1 border-0">Sin dependencias</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-2 border rounded bg-light h-100">
                    <span class="text-muted fw-bold small text-uppercase d-block mb-1">
                        <i class="fas fa-users-cog me-1 text-primary"></i>Equipo RACI
                    </span>
                    <ul id="ulRaci" class="list-group list-group-sm small border-0 bg-transparent">
                        <li class="list-group-item bg-transparent text-muted px-0 py-1 border-0">Sin asignaciones</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Procedimiento / Instructivo -->
        <div class="card border-0 shadow-sm mb-3" style="background: #fafbfc; border: 1px solid var(--cu-border) !important;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                <span class="fw-bold small text-dark"><i class="fas fa-book-reader me-2 text-primary"></i>Instructivo Operativo</span>
                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">Procedimiento</span>
            </div>
            <div class="card-body p-3" id="divInstructivoContenido" style="max-height: 160px; overflow-y: auto;">
                <span class="text-muted small">Sin instructivo operativo formal asociado.</span>
            </div>
        </div>

        <!-- Evidencias Adjuntas -->
        <div class="card border-0 shadow-sm mb-3" style="background: #ffffff; border: 1px solid var(--cu-border) !important;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                <span class="fw-bold small text-dark"><i class="fas fa-paperclip me-2 text-info"></i>Evidencias y Documentos Probatorios</span>
                <a href="evidencias.php" class="btn btn-xs btn-outline-primary" target="_blank">
                    Ir a Bandeja <i class="fas fa-external-link-alt ms-1"></i>
                </a>
            </div>
            <div class="card-body p-2" id="divTareaEvidencias" style="max-height: 160px; overflow-y: auto;">
                <span class="text-muted small">Sin evidencias registradas.</span>
            </div>
        </div>

        <!-- Hilo de Discusión / Chat de Tarea -->
        <div class="card border-0 shadow-sm mb-2" style="background: #ffffff; border: 1px solid var(--cu-border) !important;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                <span class="fw-bold small text-dark"><i class="fas fa-comments me-2 text-primary"></i>Actividad y Comentarios</span>
                <span class="badge rounded-pill font-monospace" id="badgeTotalMensajesModal" style="background: var(--cu-purple); color: #ffffff;">0</span>
            </div>
            <div class="card-body p-3">
                <div class="chat-thread-container mb-3" id="divTareaMensajes">
                    <span class="text-muted small">Cargando comentarios...</span>
                </div>
                <form id="formAgregarMensaje">
                    <input type="hidden" id="msgCicloTareaId" name="ciclo_tarea_id" value="">
                    <div class="input-group input-group-sm">
                        <input type="text" id="msgTexto" name="mensaje" class="form-control shadow-none" placeholder="Escribir un comentario o nota de equipo..." autocomplete="off" required>
                        <button type="submit" class="btn btn-primary px-3 shadow-none" id="btnEnviarMensaje" style="background: var(--cu-purple); border-color: var(--cu-purple);">
                            <i class="fas fa-paper-plane me-1"></i>Comentar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Estado de Tarea -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2" id="headerModalTareaAccion">
                <h5 class="modal-title" id="tituloModalTareaAccion">Transición de Tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCambiarEstado">
                <input type="hidden" id="transTareaId" name="ciclo_tarea_id">
                <input type="hidden" id="transNuevoEstado" name="nuevo_estado_id">
                <div class="modal-body">
                    <div class="alert py-2" id="alertaTextoTareaAccion"></div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm" id="lblObservacionesTrans">Observaciones / Evidencia</label>
                        <textarea id="transObservaciones" name="observaciones" class="form-control form-control-sm" rows="3" placeholder="Ingrese comentarios o detalles de ejecución..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnConfirmarTrans">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
<script src="ciclos_tareas.js?v=<?= time() ?>"></script>
</body>
</html>
