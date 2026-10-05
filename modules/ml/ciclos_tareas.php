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
</style>

<main class="app-main">
    <div class="app-content-header pb-0 border-bottom">
        <div class="container-fluid">
            <!-- Barra Superior: Título y Selector de Vistas de Microsoft Planner -->
            <div class="row align-items-center mb-2">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-tasks text-primary me-2"></i>Tareas de Cumplimiento Legal
                    </h3>
                    <small class="text-muted">Modelo Microsoft Planner • Monitoreo de ejecución, dependencias y revisiones</small>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <div class="d-inline-flex align-items-center">
                        <span class="badge bg-light text-dark border me-2 py-2 px-3" id="badgeTotalTareas">
                            Cargando tareas...
                        </span>
                        <button id="btnRecargarPlanner" class="btn btn-sm btn-outline-secondary" title="Actualizar datos">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Selector de Vistas (Tabs) -->
            <ul class="nav planner-nav-tabs" id="plannerViewTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-board" data-bs-toggle="tab" href="#view-board" role="tab">
                        <i class="fas fa-columns me-1"></i>Panel (Tablero)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-grid" data-bs-toggle="tab" href="#view-grid" role="tab">
                        <i class="fas fa-table me-1"></i>Cuadrícula (Lista)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-charts" data-bs-toggle="tab" href="#view-charts" role="tab">
                        <i class="fas fa-chart-pie me-1"></i>Gráficos y Métricas
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="app-content pt-3">
        <div class="container-fluid">

            <!-- ============================================================ -->
            <!-- BARRA DE HERRAMIENTAS: AGRUPAR POR + FILTROS DE PLANNER       -->
            <!-- ============================================================ -->
            <div class="card shadow-sm border-0 mb-3 bg-white">
                <div class="card-body py-2 px-3">
                    <div class="row align-items-center g-2">
                        <!-- Buscador instantáneo -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="plannerSearch" class="form-control border-start-0" placeholder="Filtrar por tarea o miembro...">
                            </div>
                        </div>

                        <!-- Dropdown "Agrupar por" (Group by de Planner) -->
                        <div class="col-6 col-md-3">
                            <div class="d-flex align-items-center">
                                <label class="small text-muted fw-bold me-2 text-nowrap"><i class="fas fa-layer-group me-1"></i>Agrupar:</label>
                                <select id="plannerGroupBy" class="form-select form-select-sm shadow-none">
                                    <option value="estado" selected>Estado (Progreso)</option>
                                    <option value="responsable">Asignado a (Responsable)</option>
                                    <option value="entidad">Depósito (Instalación / Tanque)</option>
                                    <option value="vencimiento">Fecha de Vencimiento (Plazo)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Filtro de Entidad Sujeta a Control -->
                        <div class="col-6 col-md-3">
                            <select id="filtroEntidad" class="form-select form-select-sm">
                                <option value="">-- Todas las instalaciones --</option>
                            </select>
                        </div>

                        <!-- Píldoras Rápidas de Filtro de Planner -->
                        <div class="col-12 col-md-3 text-md-end text-start">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-danger btn-filtro-planner" data-filter="vencidas" title="Con retraso">
                                    <i class="fas fa-exclamation-circle me-1"></i>Con retraso
                                </button>
                                <button type="button" class="btn btn-outline-warning text-dark btn-filtro-planner" data-filter="proximas" title="Próximas">
                                    <i class="fas fa-clock me-1"></i>Próximas
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-filtro-planner active" data-filter="todas" title="Todas">
                                    Todas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- CONTENIDO DE LAS PESTAÑAS (VISTAS DE PLANNER)                -->
            <!-- ============================================================ -->
            <div class="tab-content" id="plannerTabContent">

                <!-- 1. VISTA TABLERO KANBAN (BOARD) -->
                <div class="tab-pane fade show active" id="view-board" role="tabpanel">
                    <div class="planner-board" id="plannerBoardContainer">
                        <div class="w-100 text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            Cargando tablero Planner...
                        </div>
                    </div>
                </div>

                <!-- 2. VISTA CUADRÍCULA (GRID / LISTA DETALLADA) -->
                <div class="tab-pane fade" id="view-grid" role="tabpanel">
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

                <!-- 3. VISTA GRÁFICOS (CHARTS / ANALYTICS) -->
                <div class="tab-pane fade" id="view-charts" role="tabpanel">
                    <div class="row g-3">
                        <!-- Gráfico 1: Estado General -->
                        <div class="col-12 col-md-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-2 fw-bold">
                                    <i class="fas fa-chart-pie text-primary me-2"></i>Estado de Tareas
                                </div>
                                <div class="card-body p-3 text-center">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="p-2 border rounded bg-danger bg-opacity-10 border-danger">
                                                <small class="text-danger fw-bold d-block">Con retraso</small>
                                                <h3 class="mb-0 fw-bold text-danger" id="chartCountOverdue">0</h3>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded bg-primary bg-opacity-10 border-primary">
                                                <small class="text-primary fw-bold d-block">En curso</small>
                                                <h3 class="mb-0 fw-bold text-primary" id="chartCountProgress">0</h3>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded bg-warning bg-opacity-10 border-warning">
                                                <small class="text-dark fw-bold d-block">Próximas</small>
                                                <h3 class="mb-0 fw-bold text-dark" id="chartCountUpcoming">0</h3>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded bg-success bg-opacity-10 border-success">
                                                <small class="text-success fw-bold d-block">Cumplidas</small>
                                                <h3 class="mb-0 fw-bold text-success" id="chartCountCompleted">0</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 12px;" id="chartProgressBar">
                                        <div class="progress-bar bg-danger" id="barOverdue" style="width: 0%"></div>
                                        <div class="progress-bar bg-warning" id="barUpcoming" style="width: 0%"></div>
                                        <div class="progress-bar bg-primary" id="barProgress" style="width: 0%"></div>
                                        <div class="progress-bar bg-success" id="barCompleted" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 2: Tareas por Responsable / Miembro -->
                        <div class="col-12 col-md-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-2 fw-bold">
                                    <i class="fas fa-users text-info me-2"></i>Tareas por Responsable
                                </div>
                                <div class="card-body p-3" id="chartMembersList" style="max-height: 380px; overflow-y: auto;">
                                    <p class="text-muted text-center my-4">Cargando distribución de miembros...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 3: Tareas por Depósito / Entidad -->
                        <div class="col-12 col-md-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-2 fw-bold">
                                    <i class="fas fa-cubes text-secondary me-2"></i>Tareas por Instalación / Tanque
                                </div>
                                <div class="card-body p-3" id="chartBucketsList" style="max-height: 380px; overflow-y: auto;">
                                    <p class="text-muted text-center my-4">Cargando depósitos...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<!-- Modal: Detalle de Tarea e Instructivo de Procedimiento (Estilo Planner) -->
<div class="modal fade" id="modalDetalleTarea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-primary text-white py-2">
                <h5 class="modal-title" id="modalDetalleTareaTitulo">
                    <i class="fas fa-tasks me-2"></i>Detalle de Tarea
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Info Encabezado -->
                <div class="p-3 border rounded bg-light mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 id="dtTareaTitulo" class="fw-bold mb-0 text-dark"></h5>
                        <span id="dtEstadoBadge"></span>
                    </div>
                    <div class="row g-2 text-muted small">
                        <div class="col-md-6">
                            <strong><i class="fas fa-industry me-1"></i>Instalación: </strong><span id="dtCicloEntidad" class="text-dark"></span><br>
                            <strong><i class="fas fa-file-contract me-1"></i>Obligación: </strong><span id="dtObligacion" class="text-dark"></span>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-calendar-alt me-1"></i>Vencimiento: </strong><span id="dtVencimiento" class="fw-bold"></span><br>
                            <strong><i class="fas fa-check-circle me-1"></i>Fecha Cierre: </strong><span id="dtFechaCierre" class="text-dark">--</span>
                        </div>
                    </div>
                </div>

                <!-- Predecesoras y Matriz RACI -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-1 text-primary fw-bold"><i class="fas fa-sitemap me-1"></i>Predecesoras Obligatorias</h6>
                        <ul id="ulPredecesoras" class="list-group list-group-sm small">
                            <li class="list-group-item text-muted">Sin dependencias predecesoras</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-1 text-primary fw-bold"><i class="fas fa-users-cog me-1"></i>Matriz RACI Asignada</h6>
                        <ul id="ulRaci" class="list-group list-group-sm small">
                            <li class="list-group-item text-muted">Sin asignaciones registradas</li>
                        </ul>
                    </div>
                </div>

                <!-- Instructivo de Trabajo / Procedimiento -->
                <div class="card border-info mb-2">
                    <div class="card-header bg-info bg-opacity-10 text-info fw-bold py-2">
                        <i class="fas fa-book-reader me-2"></i>Instructivo Operativo / Procedimiento Vinculado
                    </div>
                    <div class="card-body p-2" id="divInstructivoContenido" style="max-height: 220px; overflow-y: auto; background-color: #fafbfc;">
                        <span class="text-muted small">Sin instructivo operativo formal asociado.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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
