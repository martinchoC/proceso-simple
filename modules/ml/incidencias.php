<?php
/**
 * Vista de Hallazgos e Incidencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/incidencias.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Hallazgos e Incidencias";
$currentPage = 'incidencias';
$modudo_idx = 3;
$pagina_idx = 353; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Hallazgos e Incidencias
                    </h3>
                    <small class="text-muted">Registro de no conformidades, auditorías internas y planes de acción correctivos (C13, C29)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ejecución y Seguimiento</li>
                        <li class="breadcrumb-item active" aria-current="page">Hallazgos e Incidencias</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-warning mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Entidad Sujeta a Control</label>
                            <select id="filtroEntidad" class="form-select form-select-sm">
                                <option value="">-- Todas las entidades --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">ID Ciclo Operativo</label>
                            <input type="number" id="filtroCiclo" class="form-control form-control-sm" placeholder="Ej: 10">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado del Hallazgo</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                                <option value="1" selected>Abiertas (Pendientes)</option>
                                <option value="2">Cerradas / Subsanadas</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6 text-end pt-3">
                            <button id="btnNuevaIncidencia" class="btn btn-sm btn-warning me-1" title="Registrar nuevo hallazgo">
                                <i class="fas fa-plus me-1"></i>Reportar Hallazgo
                            </button>
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Incidencias -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaIncidencias" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Entidad</th>
                                    <th>Ciclo / Obligación</th>
                                    <th>Desviación / Hallazgo</th>
                                    <th>Reportado En</th>
                                    <th>Ciclo Seguimiento</th>
                                    <th>Estado</th>
                                    <th style="width: 100px;" class="text-center">Acciones</th>
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

<!-- Modal Nuevo Hallazgo -->
<div class="modal fade" id="modalNuevaIncidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formNuevaIncidencia">
                <div class="modal-header bg-warning py-2">
                    <h5 class="modal-title fs-6 text-dark">
                        <i class="fas fa-exclamation-circle me-1"></i>Reportar Hallazgo / Incidencia de Control
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1 fw-bold">Ciclo Operativo Afectado <span class="text-danger">*</span></label>
                            <select id="modalCicloId" name="ciclo_id" class="form-select form-select-sm" required>
                                <option value="">-- Seleccionar Ciclo --</option>
                            </select>
                            <small class="text-muted">Seleccione el ciclo donde se detectó el incumplimiento o desviación operativa.</small>
                        </div>
                        <div class="col-12 mt-2">
                            <label class="form-label form-label-sm mb-1 fw-bold">Descripción del Hallazgo / Desviación <span class="text-danger">*</span></label>
                            <textarea id="modalDescripcion" name="descripcion" class="form-control form-control-sm" rows="4" placeholder="Describa el hecho detectado, estándar vulnerado o evidencia faltante..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning" id="btnGuardarIncidencia">
                        <i class="fas fa-save me-1"></i>Registrar Hallazgo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Vincular Seguimiento C29 -->
<div class="modal fade" id="modalVincularSeguimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formVincularSeguimiento">
                <input type="hidden" id="vincularIncidenciaId" name="incidencia_id">
                <div class="modal-header bg-info text-white py-2">
                    <h5 class="modal-title fs-6">
                        <i class="fas fa-calendar-check me-1"></i>Vincular Ciclo de Seguimiento Correctivo (C29)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle me-1"></i>Se creará un nuevo ciclo operativo de tipo <strong>Seguimiento</strong> enlazado a la misma obligación, con plazo de 15 días y trazabilidad completa.
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm mb-1 fw-bold">Plan de Acción Correctivo <span class="text-danger">*</span></label>
                        <textarea id="vincularPlanAccion" name="plan_accion" class="form-control form-control-sm" rows="3" placeholder="Detalle las medidas correctivas que se deberán ejecutar para subsanar este hallazgo..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-info text-white" id="btnConfirmarSeguimiento">
                        <i class="fas fa-link me-1"></i>Generar Ciclo de Seguimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cerrar Incidencia -->
<div class="modal fade" id="modalCerrarIncidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCerrarIncidencia">
                <input type="hidden" id="cerrarIncidenciaId" name="incidencia_id">
                <div class="modal-header bg-success text-white py-2">
                    <h5 class="modal-title fs-6">
                        <i class="fas fa-check-circle me-1"></i>Cerrar / Subsanar Hallazgo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Confirme el cierre de la incidencia indicando el justificativo o evidencia de subsanación:</p>
                    <div class="mb-3">
                        <label class="form-label form-label-sm mb-1 fw-bold">Motivo de Cierre / Subsanación <span class="text-danger">*</span></label>
                        <textarea id="cerrarMotivo" name="motivo" class="form-control form-control-sm" rows="3" placeholder="Indique cómo fue subsanado o resuelto el hallazgo..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success" id="btnConfirmarCierre">
                        <i class="fas fa-check me-1"></i>Dar por Subsanada
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle Incidencia -->
<div class="modal fade" id="modalDetalleIncidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h5 class="modal-title fs-6">
                    <i class="fas fa-info-circle me-1"></i>Detalle de Incidencia
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalleIncidenciaBody">
                <!-- Dinámico -->
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
<script src="incidencias.js?v=<?= time() ?>"></script>
