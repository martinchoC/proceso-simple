<?php
/**
 * Vista de Reglas y Periodicidades (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_reglas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Reglas y Periodicidades";
$currentPage = 'obligaciones_reglas';
$modudo_idx = 3;
$pagina_idx = 322; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>Reglas de Vencimiento, Periodicidades y Criticidades
                    </h3>
                    <small class="text-muted">Parámetros de cadencia, ponderación y fórmulas de cálculo de plazos legales</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reglas de Obligaciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Pestañas -->
            <ul class="nav nav-tabs mb-3" id="tabReglas" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-periodicidades-tab" data-bs-toggle="tab" data-bs-target="#tab-periodicidades" type="button" role="tab">
                        <i class="fas fa-redo-alt me-1"></i>Periodicidades (Casos C07, C26)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-vencimientos-tab" data-bs-toggle="tab" data-bs-target="#tab-vencimientos" type="button" role="tab">
                        <i class="fas fa-calculator me-1"></i>Reglas de Vencimiento (Casos C22, C23)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-criticidades-tab" data-bs-toggle="tab" data-bs-target="#tab-criticidades" type="button" role="tab">
                        <i class="fas fa-thermometer-half me-1"></i>Niveles de Criticidad y Ponderación
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="tabReglasContent">
                <!-- PESTAÑA 1: PERIODICIDADES -->
                <div class="tab-pane fade show active" id="tab-periodicidades" role="tabpanel">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row align-items-center">
                                <div class="col-sm-6"><span class="text-muted fw-semibold">Cadencias de repetición fija, plurianual o activada por eventos</span></div>
                                <div class="col-sm-6 text-sm-end">
                                    <div id="btn-container-per" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargar" data-tab="periodicidades" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaPeriodicidades" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">ID</th>
                                            <th style="width: 140px;">Código</th>
                                            <th>Denominación</th>
                                            <th style="width: 130px;" class="text-center">Intervalo</th>
                                            <th style="width: 120px;" class="text-center">Tipo Evento</th>
                                            <th style="width: 140px;" class="text-center">Fecha Autoridad</th>
                                            <th style="width: 80px;" class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 2: REGLAS DE VENCIMIENTO -->
                <div class="tab-pane fade" id="tab-vencimientos" role="tabpanel">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row align-items-center">
                                <div class="col-sm-6"><span class="text-muted fw-semibold">Fórmulas de cálculo del día exacto de vencimiento</span></div>
                                <div class="col-sm-6 text-sm-end">
                                    <div id="btn-container-venc" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargar" data-tab="vencimientos" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaVencimientos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">ID</th>
                                            <th style="width: 180px;">Código de Regla</th>
                                            <th>Nombre de la Regla</th>
                                            <th style="width: 140px;">Tipo de Fórmula</th>
                                            <th style="width: 120px;" class="text-center">Desplazamiento</th>
                                            <th style="width: 80px;" class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 3: CRITICIDADES -->
                <div class="tab-pane fade" id="tab-criticidades" role="tabpanel">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row align-items-center">
                                <div class="col-sm-6"><span class="text-muted fw-semibold">Pesos numéricos para el cálculo del indicador de cumplimiento ponderado</span></div>
                                <div class="col-sm-6 text-sm-end">
                                    <div id="btn-container-crit" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargar" data-tab="criticidades" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaCriticidades" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">ID</th>
                                            <th>Nivel de Criticidad</th>
                                            <th style="width: 120px;" class="text-center">Peso Ponderado</th>
                                            <th style="width: 80px;" class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal: Periodicidad -->
<div class="modal fade" id="modalPeriodicidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formPeriodicidad" novalidate>
                <input type="hidden" id="per_id" name="periodicidad_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-redo-alt me-1"></i>Periodicidad de Obligación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-uppercase" id="per_codigo" name="codigo" required maxlength="30">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Denominación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="per_nombre" name="nombre" required maxlength="100">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-semibold">Cantidad</label>
                            <input type="number" class="form-control form-control-sm" id="per_cantidad" name="intervalo_cantidad" min="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-semibold">Unidad</label>
                            <select class="form-select form-select-sm" id="per_unidad" name="intervalo_unidad">
                                <option value="">-- Sin unidad --</option>
                                <option value="DAY">Días</option>
                                <option value="HOUR">Horas</option>
                                <option value="MONTH">Meses</option>
                                <option value="YEAR">Años</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="per_es_evento" name="es_evento" value="1">
                        <label class="form-check-label form-label-sm" for="per_es_evento">¿Activada por Evento o Incidente? (Caso C07)</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="per_fija_autoridad" name="fecha_fija_autoridad" value="1">
                        <label class="form-check-label form-label-sm" for="per_fija_autoridad">¿Fecha fijada por la Autoridad? (Caso C26)</label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Regla Vencimiento -->
<div class="modal fade" id="modalRegla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formRegla" novalidate>
                <input type="hidden" id="venc_id" name="vencimiento_regla_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-calculator me-1"></i>Regla de Vencimiento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-uppercase" id="venc_codigo" name="codigo" required maxlength="50">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Denominación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="venc_nombre" name="nombre" required maxlength="150">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Tipo de Fórmula <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="venc_tipo" name="tipo" required>
                            <option value="CALENDARIO">Día fijo del calendario</option>
                            <option value="DATO_EMPRESA">Según dato de la Empresa (ej: CUIT/NIF - C22)</option>
                            <option value="DATO_ENTIDAD">Según permiso o habilitación propia (C23)</option>
                            <option value="EVENTO">Horas corridas desde evento (C07)</option>
                            <option value="OFFSET">Días corridos desde vigencia</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Desplazamiento en Días (+ o -)</label>
                        <input type="number" class="form-control form-control-sm" id="venc_desplazamiento" name="desplazamiento_dias" value="0">
                        <small class="text-muted fs-8">Ej: -30 para renovar un mes antes del vencimiento del permiso</small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Criticidad -->
<div class="modal fade" id="modalCriticidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formCriticidad" novalidate>
                <input type="hidden" id="crit_id" name="criticidad_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-thermometer-half me-1"></i>Nivel de Criticidad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Nombre del Nivel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="crit_nombre" name="nombre" required maxlength="50">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Peso Ponderado (1 a 100) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm" id="crit_peso" name="peso" required min="1" max="100" value="1">
                        <small class="text-muted fs-8">Utilizado en el cálculo del indicador ponderado de cumplimiento (Opción 8)</small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="obligaciones_reglas.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
