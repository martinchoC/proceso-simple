<?php
/**
 * Vista de Catálogos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_catalogos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Catálogos Normativos";
$currentPage = 'normas_catalogos';
$modudo_idx = 3;
$pagina_idx = 312; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-balance-scale me-2 text-primary"></i>Catálogos Normativos
                    </h3>
                    <small class="text-muted">Gestión de organismos emisores, tipos de normas, jerarquías de prelación y relaciones</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Catálogos Normativos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Pestañas de navegación -->
            <ul class="nav nav-tabs mb-3" id="tabCatalogos" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-organismos-tab" data-bs-toggle="tab" data-bs-target="#tab-organismos" type="button" role="tab" aria-controls="tab-organismos" aria-selected="true">
                        <i class="fas fa-university me-1"></i>Organismos Emisores
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-tipos-tab" data-bs-toggle="tab" data-bs-target="#tab-tipos" type="button" role="tab" aria-controls="tab-tipos" aria-selected="false">
                        <i class="fas fa-tags me-1"></i>Tipos de Normas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-jerarquias-tab" data-bs-toggle="tab" data-bs-target="#tab-jerarquias" type="button" role="tab" aria-controls="tab-jerarquias" aria-selected="false">
                        <i class="fas fa-layer-group me-1"></i>Jerarquías de Prelación
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-relaciones-tab" data-bs-toggle="tab" data-bs-target="#tab-relaciones" type="button" role="tab" aria-controls="tab-relaciones" aria-selected="false">
                        <i class="fas fa-project-diagram me-1"></i>Tipos de Relaciones
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="tabCatalogosContent">
                <!-- TAB 1: ORGANISMOS -->
                <div class="tab-pane fade show active" id="tab-organismos" role="tabpanel" aria-labelledby="tab-organismos-tab">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4 col-sm-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                        <select id="filtroOrganismoTerritorio" class="form-select form-select-sm">
                                            <option value="">-- Todos los territorios / Jurisdicción --</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <select id="filtroOrganismoEstado" class="form-select form-select-sm">
                                        <option value="1">Activos</option>
                                        <option value="2">Inactivos</option>
                                        <option value="-1">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-5 col-sm-12 text-md-end">
                                    <div id="btn-container-organismo" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargarTab" data-tab="organismos" title="Recargar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaOrganismos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th>Nombre del Organismo</th>
                                            <th>Territorio / Jurisdicción</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
                                            <th style="width: 80px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: TIPOS DE NORMAS -->
                <div class="tab-pane fade" id="tab-tipos" role="tabpanel" aria-labelledby="tab-tipos-tab">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4 col-sm-6">
                                    <select id="filtroTipoEstado" class="form-select form-select-sm">
                                        <option value="1">Activos</option>
                                        <option value="2">Inactivos</option>
                                        <option value="-1">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-8 col-sm-6 text-end">
                                    <div id="btn-container-tipo" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargarTab" data-tab="tipos" title="Recargar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaTipos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th style="width: 140px;">Código</th>
                                            <th>Nombre / Descripción</th>
                                            <th style="width: 120px;" class="text-center">¿Es Legal?</th>
                                            <th style="width: 150px;" class="text-center">¿Genera Obligaciones?</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
                                            <th style="width: 80px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: JERARQUÍAS -->
                <div class="tab-pane fade" id="tab-jerarquias" role="tabpanel" aria-labelledby="tab-jerarquias-tab">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-4 col-sm-6">
                                    <select id="filtroJerarquiaEstado" class="form-select form-select-sm">
                                        <option value="1">Activos</option>
                                        <option value="2">Inactivos</option>
                                        <option value="-1">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-8 col-sm-6 text-end">
                                    <div id="btn-container-jerarquia" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargarTab" data-tab="jerarquias" title="Recargar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaJerarquias" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th style="width: 90px;" class="text-center">Nivel</th>
                                            <th>Jerarquía / Prelación Legal</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
                                            <th style="width: 80px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: TIPOS DE RELACIONES -->
                <div class="tab-pane fade" id="tab-relaciones" role="tabpanel" aria-labelledby="tab-relaciones-tab">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <span class="text-muted fw-semibold"><i class="fas fa-info-circle me-1"></i>Catálogo estándar de interconexión jurídica entre normas (Solo Lectura)</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaRelacionesTipos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th style="width: 160px;">Código de Vínculo</th>
                                            <th>Efecto Jurídico / Descripción</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
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

<!-- Modal Organismo -->
<div class="modal fade" id="modalOrganismo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formOrganismo" novalidate>
                <input type="hidden" id="org_id" name="organismo_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalOrgTitle"><i class="fas fa-university me-1"></i>Organismo Emisor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Nombre del Organismo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="org_nombre" name="nombre" required maxlength="200" placeholder="Ej: Superintendencia de Riesgos del Trabajo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Territorio / País de Radicación</label>
                        <select class="form-select form-select-sm" id="org_territorio_id" name="territorio_id">
                            <option value="">-- Internacional / Sin territorio único --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Estado</label>
                        <select class="form-select form-select-sm" id="org_estado" name="tabla_estado_registro_id">
                            <option value="1">Activo</option>
                            <option value="2">Inactivo</option>
                        </select>
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

<!-- Modal Tipo Norma -->
<div class="modal fade" id="modalTipoNorma" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formTipoNorma" novalidate>
                <input type="hidden" id="tipo_id" name="norma_tipo_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalTipoTitle"><i class="fas fa-tag me-1"></i>Tipo de Norma</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-uppercase" id="tipo_codigo" name="codigo" required maxlength="30" placeholder="Ej: LEY, DECRETO, POLITICA">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Nombre Descriptivo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="tipo_nombre" name="nombre" required maxlength="100">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="tipo_es_legal" name="es_legal" value="1">
                                <label class="form-check-label form-label-sm" for="tipo_es_legal">¿Es fuente legal?</label>
                            </div>
                            <small class="text-muted fs-8">Exige dictamen de Legales ante excepciones</small>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="tipo_genera_obligaciones" name="genera_obligaciones" value="1" checked>
                                <label class="form-check-label form-label-sm" for="tipo_genera_obligaciones">¿Genera Obligaciones?</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Estado</label>
                        <select class="form-select form-select-sm" id="tipo_estado" name="tabla_estado_registro_id">
                            <option value="1">Activo</option>
                            <option value="2">Inactivo</option>
                        </select>
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

<!-- Modal Jerarquía -->
<div class="modal fade" id="modalJerarquia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formJerarquia" novalidate>
                <input type="hidden" id="jer_id" name="norma_jerarquia_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalJerTitle"><i class="fas fa-layer-group me-1"></i>Nivel de Jerarquía</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Nivel Numérico <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm" id="jer_nivel" name="nivel" required min="1" max="99" value="1">
                        <small class="text-muted fs-8">Menor número representa mayor jerarquía de prelación (1 es la máxima)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Nombre / Descripción de la Jerarquía <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="jer_nombre" name="nombre" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Estado</label>
                        <select class="form-select form-select-sm" id="jer_estado" name="tabla_estado_registro_id">
                            <option value="1">Activo</option>
                            <option value="2">Inactivo</option>
                        </select>
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
<script src="normas_catalogos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
