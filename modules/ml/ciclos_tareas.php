<?php
/**
 * Vista de Tareas Operativas (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ciclos_tareas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Tareas Operativas";
$currentPage = 'ciclos_tareas';
$modudo_idx = 3;
$pagina_idx = 343; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tasks me-2 text-primary"></i>Tareas Operativas
                    </h3>
                    <small class="text-muted">Bandeja de ejecución, dependencias predecesoras, revisión técnica e instructivos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ciclos y Tareas</li>
                        <li class="breadcrumb-item active" aria-current="page">Tareas Operativas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Entidad Sujeta a Control</label>
                            <select id="filtroEntidad" class="form-select form-select-sm">
                                <option value="">-- Todas las entidades --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Responsable Asignado</label>
                            <select id="filtroUsuario" class="form-select form-select-sm">
                                <option value="">-- Todos los responsables --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado Tarea</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">ID Ciclo</label>
                            <input type="number" id="filtroCiclo" class="form-control form-control-sm" placeholder="Ej: 10">
                        </div>
                        <div class="col-md-2 col-sm-4 text-end pt-3">
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Tareas Operativas -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaTareas" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Entidad</th>
                                    <th>Obligación</th>
                                    <th>Tarea Operativa</th>
                                    <th style="width: 130px;">Responsable (R)</th>
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
</main>

<!-- Modal: Detalle de Tarea e Instructivo de Procedimiento -->
<div class="modal fade" id="modalDetalleTarea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="modalDetalleTareaTitulo">
                    <i class="fas fa-file-alt me-2"></i>Detalle de Tarea Operativa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Info Encabezado -->
                <div class="p-2 border rounded bg-light mb-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <strong>Tarea: </strong><span id="dtTareaTitulo" class="fw-semibold"></span><br>
                            <strong>Ciclo / Entidad: </strong><span id="dtCicloEntidad"></span><br>
                            <strong>Obligación: </strong><span id="dtObligacion"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Vencimiento: </strong><span id="dtVencimiento" class="text-danger fw-bold"></span><br>
                            <strong>Estado: </strong><span id="dtEstadoBadge"></span><br>
                            <strong>Cierre: </strong><span id="dtFechaCierre">--</span>
                        </div>
                    </div>
                </div>

                <!-- Predecesoras y Matriz RACI -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-1 text-primary"><i class="fas fa-sitemap me-1"></i>Predecesoras Obligatorias</h6>
                        <ul id="ulPredecesoras" class="list-group list-group-sm small">
                            <li class="list-group-item text-muted">Sin dependencias predecesoras</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-1 text-primary"><i class="fas fa-users-cog me-1"></i>Asignación RACI</h6>
                        <ul id="ulRaci" class="list-group list-group-sm small">
                            <li class="list-group-item text-muted">Sin asignaciones registradas</li>
                        </ul>
                    </div>
                </div>

                <!-- Instructivo de Trabajo / Procedimiento (Caso C14) -->
                <div class="card border-info mb-2">
                    <div class="card-header bg-info text-white py-1">
                        <i class="fas fa-book-reader me-2"></i>Instructivo Operativo / Procedimiento Vinculado
                    </div>
                    <div class="card-body p-2" id="divInstructivoContenido" style="max-height: 250px; overflow-y: auto; background-color: #fcfcfc;">
                        <span class="text-muted small">Sin instructivo operativo formal asociado.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Estado de Tarea -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
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
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnConfirmarTrans">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="modules/ml/ciclos_tareas.js"></script>
</body>
</html>
