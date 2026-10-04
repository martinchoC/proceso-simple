<?php
/**
 * Vista de Matriz de Obligaciones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Matriz de Obligaciones";
$currentPage = 'obligaciones';
$modudo_idx = 3;
$pagina_idx = 323; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-clipboard-check me-2 text-primary"></i>Matriz de Obligaciones
                    </h3>
                    <small class="text-muted">Requerimientos ejecutables derivados de normas, reglas de periodicidad y aplicabilidad por entidad</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Obligaciones</li>
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
                            <label class="form-label form-label-sm mb-1 text-muted">Norma de Origen</label>
                            <select id="filtroNorma" class="form-select form-select-sm">
                                <option value="">-- Todas las normas --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Periodicidad</label>
                            <select id="filtroPeriodicidad" class="form-select form-select-sm">
                                <option value="">-- Todas --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Criticidad</label>
                            <select id="filtroCriticidad" class="form-select form-select-sm">
                                <option value="">-- Todas --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                                <option value="2061">Borrador</option>
                                <option value="2062">Pendiente de Aprobación</option>
                                <option value="2063" selected>Confirmada / Activa</option>
                                <option value="2064">Suspendida / De Baja</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-12 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-obligacion" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Obligaciones -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaObligaciones" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">ID</th>
                                    <th style="width: 130px;">Código</th>
                                    <th>Descripción y Requisito</th>
                                    <th>Norma de Origen</th>
                                    <th style="width: 130px;">Periodicidad</th>
                                    <th style="width: 140px;">Regla Vencimiento</th>
                                    <th style="width: 90px;" class="text-center">Criticidad</th>
                                    <th style="width: 80px;" class="text-center">Evidencia</th>
                                    <th style="width: 100px;" class="text-center">Estado</th>
                                    <th style="width: 140px;" class="text-center">Acciones</th>
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

<!-- Modal: Alta y Edición de Obligación -->
<div class="modal fade" id="modalObligacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formObligacion" novalidate>
                <input type="hidden" id="obl_id" name="obligacion_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalOblLabel"><i class="fas fa-clipboard-check me-1"></i>Obligación Legal / Corporativa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-7">
                            <label class="form-label form-label-sm fw-semibold">Norma de Origen <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="obl_norma_id" name="norma_id" required>
                                <option value="">-- Seleccionar Norma --</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-semibold">Código de Obligación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="obl_codigo" name="codigo" required maxlength="80" placeholder="Ej: OBL-SEG-01">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label class="form-label form-label-sm fw-semibold">Descripción del Requisito <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="obl_descripcion" name="descripcion" rows="2" required placeholder="Texto formal de la obligación a cumplir..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Artículo de Referencia</label>
                            <input type="text" class="form-control form-control-sm" id="obl_articulo" name="articulo_referencia" placeholder="Ej: Art. 14 inc. B">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Periodicidad <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="obl_periodicidad_id" name="periodicidad_id" required>
                                <option value="">-- Seleccionar --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Regla de Vencimiento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="obl_vencimiento_regla_id" name="vencimiento_regla_id" required>
                                <option value="">-- Seleccionar --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Criticidad / Impacto</label>
                            <select class="form-select form-select-sm" id="obl_criticidad_id" name="criticidad_id">
                                <option value="">-- Sin criticidad asignada --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-4 col-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="obl_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="obl_vigente_hasta" name="vigente_hasta">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label form-label-sm fw-semibold">Retención Evidencias (Meses)</label>
                            <input type="number" class="form-control form-control-sm" id="obl_retencion" name="retencion_evidencias_meses" value="60">
                        </div>
                    </div>
                    <div class="row g-2 p-2 bg-light border rounded">
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="obl_requiere_revision" name="requiere_revision" value="1" checked>
                                <label class="form-check-label form-label-sm" for="obl_requiere_revision">¿Exige Revisión Técnica?</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="obl_requiere_evidencia" name="requiere_evidencia" value="1" checked>
                                <label class="form-check-label form-label-sm" for="obl_requiere_evidencia">¿Exige Evidencia Documental?</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar Obligación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Aplicabilidad por Tipos de Entidad (Caso C20) -->
<div class="modal fade" id="modalTiposEntidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-dark py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-cubes me-1"></i>Aplicabilidad por Tipo de Entidad: <span id="lblOblCodigoEnt" class="fw-bold"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <form id="formTipoEntidad" class="row g-2 align-items-end mb-3 bg-light p-2 rounded border" novalidate>
                    <input type="hidden" id="ent_obligacion_id" name="obligacion_id">
                    <div class="col-md-5">
                        <label class="form-label form-label-sm mb-0">Tipo de Entidad *</label>
                        <select class="form-select form-select-sm" id="ent_tipo_id" name="entidad_tipo_id" required>
                            <option value="">-- Seleccionar Tipo --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-0">Condición JSON (Opcional)</label>
                        <input type="text" class="form-control form-control-sm" id="ent_regla_json" name="regla_json" placeholder='{"empleados_min": 50}'>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-plus me-1"></i>Vincular</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo de Entidad</th>
                                <th>Condición Adicional</th>
                                <th style="width: 100px;" class="text-center">Desde</th>
                                <th style="width: 60px;" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="bodyTiposEntidad"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Dependencias entre Obligaciones (Caso C24) -->
<div class="modal fade" id="modalDependencias" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-link me-1"></i>Obligaciones Predecesoras: <span id="lblOblCodigoDep" class="fw-bold"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <form id="formDependencia" class="row g-2 align-items-end mb-3 bg-light p-2 rounded border" novalidate>
                    <input type="hidden" id="dep_obligacion_id" name="obligacion_id">
                    <div class="col-md-9">
                        <label class="form-label form-label-sm mb-0">Obligación Predecesora Obligatoria (Lógica Y) *</label>
                        <select class="form-select form-select-sm" id="dep_predecesora_id" name="obligacion_predecesora_id" required>
                            <option value="">-- Seleccionar Predecesora --</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-link me-1"></i>Encadenar</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Código Predecesora</th>
                                <th>Descripción</th>
                                <th style="width: 100px;" class="text-center">Desde</th>
                                <th style="width: 60px;" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="bodyDependencias"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Alertas Anticipadas -->
<div class="modal fade" id="modalAlertas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-bell me-1"></i>Alertas de Vencimiento: <span id="lblOblCodigoAle" class="fw-bold"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <form id="formAlerta" class="row g-2 align-items-end mb-3 bg-light p-2 rounded border" novalidate>
                    <input type="hidden" id="ale_obligacion_id" name="obligacion_id">
                    <div class="col-4">
                        <label class="form-label form-label-sm mb-0">Días Antes *</label>
                        <input type="number" class="form-control form-control-sm" id="ale_dias" name="dias_antes" value="15" min="0" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label form-label-sm mb-0">Destinatario</label>
                        <select class="form-select form-select-sm" id="ale_dest" name="destinatario">
                            <option value="R">Responsable (R)</option>
                            <option value="A">Aprobador (A)</option>
                            <option value="SUPERIOR">Superior Directo</option>
                            <option value="TODOS">Todos los involucrados</option>
                        </select>
                    </div>
                    <div class="col-3 text-end">
                        <button type="submit" class="btn btn-sm btn-warning w-100"><i class="fas fa-plus"></i> Alerta</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">Anticipación</th>
                                <th>Destinatario</th>
                                <th style="width: 50px;" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="bodyAlertas"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="obligaciones.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
