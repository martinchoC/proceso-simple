<?php
/**
 * Vista de Tipos de Entidad y Tipos de Datos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades_tipos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Entidad y Datos";
$currentPage = 'entidades_tipos';
$modudo_idx = 3;
$pagina_idx = 307; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tags me-2 text-primary"></i>Tipos de Entidad y Datos
                    </h3>
                    <small class="text-muted">Catálogo de clasificaciones de entidades y campos dinámicos complementarios</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Entidad</li>
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
                    <ul class="nav nav-tabs" id="tabsCatalogo" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="tab-tipos-entidad-link" data-bs-toggle="tab" data-bs-target="#tab-tipos-entidad" type="button" role="tab" aria-controls="tab-tipos-entidad" aria-selected="true">
                                <i class="fas fa-cubes me-1"></i>Tipos de Entidad
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="tab-tipos-datos-link" data-bs-toggle="tab" data-bs-target="#tab-tipos-datos" type="button" role="tab" aria-controls="tab-tipos-datos" aria-selected="false">
                                <i class="fas fa-database me-1"></i>Tipos de Datos Complementarios
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="tabsContentCatalogo">

                        <!-- TAB 1: TIPOS DE ENTIDAD -->
                        <div class="tab-pane fade show active" id="tab-tipos-entidad" role="tabpanel" aria-labelledby="tab-tipos-entidad-link">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Grupo Corporativo</label>
                                    <select id="filtroGrupoTipos" class="form-select form-select-sm">
                                        <option value="">-- Todos los grupos --</option>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                                    <select id="filtroEstadoTipos" class="form-select form-select-sm">
                                        <option value="1">Activos</option>
                                        <option value="0">Todos</option>
                                        <option value="2">Inactivos</option>
                                    </select>
                                </div>
                                <div class="col-md-7 col-sm-12 text-md-end mt-3 mt-md-auto">
                                    <button type="button" class="btn btn-sm btn-primary me-1" id="btnNuevoTipoEntidad">
                                        <i class="fas fa-plus me-1"></i>Nuevo Tipo de Entidad
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarTipos" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaTiposEntidad" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th style="width: 120px;">Código</th>
                                            <th>Nombre del Tipo</th>
                                            <th>Grupo Corporativo</th>
                                            <th style="width: 220px;" class="text-center">Características</th>
                                            <th style="width: 110px;" class="text-center">Entidades</th>
                                            <th style="width: 90px;" class="text-center">Estado</th>
                                            <th style="width: 120px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: TIPOS DE DATOS COMPLEMENTARIOS -->
                        <div class="tab-pane fade" id="tab-tipos-datos" role="tabpanel" aria-labelledby="tab-tipos-datos-link">
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                                    <select id="filtroEstadoDatos" class="form-select form-select-sm">
                                        <option value="1">Activos</option>
                                        <option value="0">Todos</option>
                                        <option value="2">Inactivos</option>
                                    </select>
                                </div>
                                <div class="col-md-9 col-sm-6 text-md-end mt-3 mt-md-auto">
                                    <button type="button" class="btn btn-sm btn-primary me-1" id="btnNuevoTipoDato">
                                        <i class="fas fa-plus me-1"></i>Nuevo Tipo de Dato
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarDatos" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaTiposDatos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th style="width: 140px;">Código Campo</th>
                                            <th>Nombre / Etiqueta</th>
                                            <th style="width: 140px;" class="text-center">Tipo de Dato</th>
                                            <th style="width: 100px;" class="text-center">Estado</th>
                                            <th style="width: 120px;" class="text-center">Acciones</th>
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

<!-- Modal: Tipo de Entidad -->
<div class="modal fade" id="modalTipoEntidad" tabindex="-1" aria-labelledby="modalTipoEntidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formTipoEntidad" novalidate>
                <input type="hidden" id="modal_tipo_entidad_id" name="entidad_tipo_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalTipoEntidadLabel">
                        <i class="fas fa-cube me-1"></i><span id="tituloModalTipo">Nuevo Tipo de Entidad</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_tipo_grupo_id" class="form-label form-label-sm fw-semibold">Grupo Corporativo <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_tipo_grupo_id" name="grupo_id" required>
                            <option value="">-- Seleccionar Grupo --</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-4 mb-3">
                            <label for="modal_tipo_codigo" class="form-label form-label-sm fw-semibold">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase font-monospace" id="modal_tipo_codigo" name="codigo" maxlength="50" required placeholder="PLANTA">
                        </div>
                        <div class="col-8 mb-3">
                            <label for="modal_tipo_nombre" class="form-label form-label-sm fw-semibold">Nombre Descriptivo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_tipo_nombre" name="nombre" maxlength="150" required placeholder="Planta Industrial">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold d-block">Clasificación Operativa</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="modal_es_fisica" name="es_fisica" value="1" checked>
                            <label class="form-check-label small" for="modal_es_fisica">Es Física</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="modal_es_logica" name="es_logica" value="1">
                            <label class="form-check-label small" for="modal_es_logica">Es Lógica</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="modal_es_movil" name="es_movil" value="1">
                            <label class="form-check-label small" for="modal_es_movil">Es Móvil (Buque/Camión)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarTipoEntidad">
                        <i class="fas fa-save me-1"></i>Guardar Tipo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Tipo de Dato -->
<div class="modal fade" id="modalTipoDato" tabindex="-1" aria-labelledby="modalTipoDatoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formTipoDato" novalidate>
                <input type="hidden" id="modal_tipo_dato_id" name="dato_tipo_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalTipoDatoLabel">
                        <i class="fas fa-database me-1"></i><span id="tituloModalDato">Nuevo Tipo de Dato</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_dato_codigo" class="form-label form-label-sm fw-semibold">Código Campo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-uppercase font-monospace" id="modal_dato_codigo" name="codigo" maxlength="50" required placeholder="CAPACIDAD_M3">
                    </div>
                    <div class="mb-3">
                        <label for="modal_dato_nombre" class="form-label form-label-sm fw-semibold">Etiqueta / Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_dato_nombre" name="nombre" maxlength="150" required placeholder="Capacidad en Metros Cúbicos">
                    </div>
                    <div class="mb-3">
                        <label for="modal_dato_tipo_valor" class="form-label form-label-sm fw-semibold">Tipo de Valor <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_dato_tipo_valor" name="tipo_valor" required>
                            <option value="TEXTO">Texto</option>
                            <option value="NUMERO">Numérico</option>
                            <option value="FECHA">Fecha</option>
                            <option value="BOOLEANO">Booleano (Sí/No)</option>
                            <option value="SELECT">Lista de Opciones</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarTipoDato">
                        <i class="fas fa-save me-1"></i>Guardar Tipo de Dato
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="entidades_tipos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
