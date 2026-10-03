<?php
/**
 * Vista de Grupos Corporativos y Regiones de Gestión (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/grupos_regiones.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Grupos Corporativos y Regiones";
$currentPage = 'grupos_regiones';
$modudo_idx = 3;
$pagina_idx = 302; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-sitemap me-2 text-primary"></i>Estructura Corporativa y Regiones
                    </h3>
                    <small class="text-muted">Grupos económicos, regiones operativas y pertenencia de empresas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Grupos y Regiones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Pestañas de Navegación -->
            <div class="card card-primary card-outline card-outline-tabs">
                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs" id="tabsEstructura" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="tab-grupos-link" data-bs-toggle="tab" data-bs-target="#tab-grupos" type="button" role="tab" aria-controls="tab-grupos" aria-selected="true">
                                <i class="fas fa-layer-group me-1"></i>Grupos Corporativos
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="tab-regiones-link" data-bs-toggle="tab" data-bs-target="#tab-regiones" type="button" role="tab" aria-controls="tab-regiones" aria-selected="false">
                                <i class="fas fa-map-marked-alt me-1"></i>Regiones de Gestión
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="tab-empresas-link" data-bs-toggle="tab" data-bs-target="#tab-empresas" type="button" role="tab" aria-controls="tab-empresas" aria-selected="false">
                                <i class="fas fa-building me-1"></i>Empresas por Región
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="tabsContentEstructura">
                        
                        <!-- TAB 1: GRUPOS CORPORATIVOS -->
                        <div class="tab-pane fade show active" id="tab-grupos" role="tabpanel" aria-labelledby="tab-grupos-link">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                                    <select id="filtroEstadoGrupos" class="form-select form-select-sm">
                                        <option value="1">Activos</option>
                                        <option value="0">Todos</option>
                                        <option value="2">Inactivos</option>
                                    </select>
                                </div>
                                <div class="col-md-9 col-sm-6 text-end mt-3 mt-md-auto">
                                    <button type="button" class="btn btn-sm btn-primary me-1" id="btnNuevoGrupo">
                                        <i class="fas fa-plus me-1"></i>Nuevo Grupo
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarGrupos" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaGrupos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 70px;" class="text-center">ID</th>
                                            <th>Nombre del Grupo Corporativo</th>
                                            <th>Empresa Titular de Cabecera</th>
                                            <th style="width: 140px;" class="text-center">Regiones</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
                                            <th style="width: 130px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: REGIONES DE GESTIÓN -->
                        <div class="tab-pane fade" id="tab-regiones" role="tabpanel" aria-labelledby="tab-regiones-link">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-4 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Filtrar por Grupo</label>
                                    <select id="filtroGrupoRegiones" class="form-select form-select-sm">
                                        <option value="">-- Todos los grupos --</option>
                                    </select>
                                </div>
                                <div class="col-md-8 col-sm-6 text-end mt-3 mt-md-auto">
                                    <button type="button" class="btn btn-sm btn-primary me-1" id="btnNuevaRegion">
                                        <i class="fas fa-plus me-1"></i>Nueva Región
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarRegiones" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaRegiones" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 70px;" class="text-center">ID</th>
                                            <th>Nombre de la Región</th>
                                            <th>Grupo Corporativo</th>
                                            <th style="width: 120px;" class="text-center">Vigente Desde</th>
                                            <th style="width: 120px;" class="text-center">Vigente Hasta</th>
                                            <th style="width: 140px;" class="text-center">Empresas</th>
                                            <th style="width: 140px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 3: EMPRESAS POR REGIÓN -->
                        <div class="tab-pane fade" id="tab-empresas" role="tabpanel" aria-labelledby="tab-empresas-link">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-4 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Región</label>
                                    <select id="filtroRegionEmpresas" class="form-select form-select-sm">
                                        <option value="">-- Todas las regiones --</option>
                                    </select>
                                </div>
                                <div class="col-md-8 col-sm-6 text-end mt-3 mt-md-auto">
                                    <button type="button" class="btn btn-sm btn-success me-1" id="btnAsignarEmpresa">
                                        <i class="fas fa-link me-1"></i>Asignar Empresa a Región
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarEmpresasRegiones" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaEmpresasRegiones" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 70px;" class="text-center">ID</th>
                                            <th>Empresa</th>
                                            <th>Región Corporativa</th>
                                            <th>Grupo</th>
                                            <th style="width: 120px;" class="text-center">Vigente Desde</th>
                                            <th style="width: 120px;" class="text-center">Vigente Hasta</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
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
        </div>
    </div>
</main>

<!-- Modal: Grupo -->
<div class="modal fade" id="modalGrupo" tabindex="-1" aria-labelledby="modalGrupoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formGrupo" novalidate>
                <input type="hidden" id="grupo_id" name="grupo_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalGrupoLabel">
                        <i class="fas fa-layer-group me-1"></i><span id="tituloModalGrupo">Nuevo Grupo</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_grupo_nombre" class="form-label form-label-sm fw-semibold">Nombre del Grupo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_grupo_nombre" name="nombre" maxlength="150" required placeholder="Ej: Grupo Corporativo Austral">
                        <div class="invalid-feedback">Indique el nombre del grupo.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_grupo_empresa_titular" class="form-label form-label-sm fw-semibold">Empresa Titular de Cabecera <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_grupo_empresa_titular" name="empresa_titular_id" required>
                            <option value="">-- Seleccionar Empresa --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione la empresa cabecera.</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarGrupo">
                        <i class="fas fa-save me-1"></i>Guardar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Región -->
<div class="modal fade" id="modalRegion" tabindex="-1" aria-labelledby="modalRegionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formRegion" novalidate>
                <input type="hidden" id="region_grupo_id" name="region_grupo_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalRegionLabel">
                        <i class="fas fa-map-marked-alt me-1"></i><span id="tituloModalRegion">Nueva Región</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_region_grupo_id" class="form-label form-label-sm fw-semibold">Grupo Corporativo <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_region_grupo_id" name="grupo_id" required>
                            <option value="">-- Seleccionar Grupo --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione el grupo al que pertenece la región.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_region_nombre" class="form-label form-label-sm fw-semibold">Nombre de la Región <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_region_nombre" name="nombre" maxlength="150" required placeholder="Ej: Cono Sur, Región Andina">
                        <div class="invalid-feedback">Indique el nombre de la región.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label for="modal_region_vigente_desde" class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="modal_region_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="modal_region_vigente_hasta" class="form-label form-label-sm fw-semibold">Vigente Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="modal_region_vigente_hasta" name="vigente_hasta">
                            <small class="text-muted fs-8">Dejar en blanco si está activa</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarRegion">
                        <i class="fas fa-save me-1"></i>Guardar Región
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Asignar Empresa a Región -->
<div class="modal fade" id="modalAsignarEmpresa" tabindex="-1" aria-labelledby="modalAsignarEmpresaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formAsignarEmpresa" novalidate>
                <div class="modal-header bg-success text-white py-2">
                    <h5 class="modal-title fs-6" id="modalAsignarEmpresaLabel">
                        <i class="fas fa-link me-1"></i>Asignar Empresa a Región
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_asignar_region_id" class="form-label form-label-sm fw-semibold">Región Corporativa <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_asignar_region_id" name="region_grupo_id" required>
                            <option value="">-- Seleccionar Región --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione la región.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_asignar_empresa_id" class="form-label form-label-sm fw-semibold">Empresa <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_asignar_empresa_id" name="empresa_id" required>
                            <option value="">-- Seleccionar Empresa --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione la empresa a asignar.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label for="modal_asignar_vigente_desde" class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="modal_asignar_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="modal_asignar_vigente_hasta" class="form-label form-label-sm fw-semibold">Vigente Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="modal_asignar_vigente_hasta" name="vigente_hasta">
                            <small class="text-muted fs-8">Opcional para asignación abierta</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success" id="btnGuardarAsignacion">
                        <i class="fas fa-save me-1"></i>Asignar Empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="grupos_regiones.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
