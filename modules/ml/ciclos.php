<?php
/**
 * Vista de Calendario y Ciclos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ciclos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Calendario y Ciclos";
$currentPage = 'ciclos';
$modudo_idx = 3;
$pagina_idx = 342; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>Calendario y Ciclos
                    </h3>
                    <small class="text-muted">Gestión de períodos, vencimientos, estados y ciclos derivados (Complementarios y Seguimiento)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ciclos y Tareas</li>
                        <li class="breadcrumb-item active" aria-current="page">Calendario y Ciclos</li>
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
                            <label class="form-label form-label-sm mb-1 text-muted">Obligación / Exigencia</label>
                            <select id="filtroObligacion" class="form-select form-select-sm">
                                <option value="">-- Todas las obligaciones --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado Ciclo</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo Vínculo</label>
                            <select id="filtroVinculo" class="form-select form-select-sm">
                                <option value="">-- Ordinarios y Vinculados --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4 text-end pt-3">
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Ciclos -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaCiclos" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Entidad</th>
                                    <th>Obligación / Criticidad</th>
                                    <th style="width: 130px;">Período</th>
                                    <th style="width: 140px;">Vencimiento</th>
                                    <th style="width: 140px;">Progreso Tareas</th>
                                    <th style="width: 110px;">Vínculo</th>
                                    <th style="width: 110px;">Estado</th>
                                    <th style="width: 90px;" class="text-center">Acciones</th>
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

<!-- Modal: Detalle de Ciclo y sus Tareas -->
<div class="modal fade" id="modalDetalleCiclo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="modalDetalleTitulo">
                    <i class="fas fa-info-circle me-2"></i>Detalle de Ciclo Operativo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-light">
                            <strong>Obligación: </strong><span id="detObligacionCodigo" class="badge bg-secondary"></span> <span id="detObligacionTitulo" class="fw-semibold"></span><br>
                            <strong>Entidad: </strong><span id="detEntidadNombre"></span> (<span id="detEntidadTipo" class="text-muted"></span>)<br>
                            <strong>Periodicidad: </strong><span id="detPeriodicidad"></span> | <strong>Criticidad: </strong><span id="detCriticidad"></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-light">
                            <strong>Período: </strong><span id="detPeriodo"></span><br>
                            <strong>Vencimiento: </strong><span id="detVencimiento" class="text-danger fw-bold"></span><br>
                            <strong>Estado: </strong><span id="detEstadoBadge"></span> | <strong>Cierre: </strong><span id="detFechaCierre">--</span>
                        </div>
                    </div>
                    <div class="col-12" id="divVinculoDetalle" style="display:none;">
                        <div class="alert alert-info py-2 mb-0">
                            <i class="fas fa-link me-1"></i> <span id="detVinculoTexto"></span>
                        </div>
                    </div>
                </div>

                <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-tasks me-2"></i>Tareas Operativas Instanciadas</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Tarea / Título</th>
                                <th style="width: 130px;">Vencimiento</th>
                                <th style="width: 90px;" class="text-center">Revisión</th>
                                <th style="width: 90px;" class="text-center">Evidencia</th>
                                <th style="width: 120px;" class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyTareasDetalle">
                            <tr><td colspan="6" class="text-center text-muted">Sin tareas registradas</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear Ciclo Complementario (Caso C28) -->
<div class="modal fade" id="modalComplementario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Crear Ciclo Complementario (Caso C28)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formComplementario">
                <input type="hidden" id="compCicloOrigenId" name="ciclo_origen_id">
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Crea un ciclo complementario con sus propias tareas operativas sin alterar el ciclo de origen.
                    </p>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Ciclo Origen</label>
                        <input type="text" id="compCicloOrigenTxt" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Fecha de Vencimiento Complementaria *</label>
                        <input type="datetime-local" id="compFechaVencimiento" name="fecha_vencimiento" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Motivo / Justificación *</label>
                        <textarea id="compMotivo" name="motivo" class="form-control form-control-sm" rows="3" placeholder="Indicar razón por la cual se complementa..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-check me-1"></i>Generar Complementario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Crear Ciclo de Seguimiento (Caso C29) -->
<div class="modal fade" id="modalSeguimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title"><i class="fas fa-route me-2"></i>Crear Ciclo de Seguimiento (Caso C29)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSeguimiento">
                <input type="hidden" id="segCicloOrigenId" name="ciclo_origen_id">
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Genera un ciclo de seguimiento con plan de acción correctivo para subsanar un incumplimiento previo.
                    </p>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Ciclo Origen Incumplido / Desviado</label>
                        <input type="text" id="segCicloOrigenTxt" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Fecha Límite Plan de Acción *</label>
                        <input type="datetime-local" id="segFechaVencimiento" name="fecha_vencimiento" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Plan de Acción / Medidas Correctivas *</label>
                        <textarea id="segPlanAccion" name="plan_accion" class="form-control form-control-sm" rows="3" placeholder="Detalle el plan de acción acordado..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-check me-1"></i>Generar Seguimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Acción Estado (Cerrar / Eximir / Cancelar) -->
<div class="modal fade" id="modalAccionEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2" id="headerModalAccion">
                <h5 class="modal-title" id="tituloModalAccion">Acción sobre Ciclo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAccionEstado">
                <input type="hidden" id="accionTipo" name="accion_tipo">
                <input type="hidden" id="accionCicloId" name="ciclo_id">
                <div class="modal-body">
                    <div class="alert alert-secondary py-2" id="alertaTextoAccion"></div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm" id="lblMotivoAccion">Observaciones / Motivo *</label>
                        <textarea id="accionMotivo" name="motivo" class="form-control form-control-sm" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnConfirmarAccion">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="modules/ml/ciclos.js"></script>
</body>
</html>
