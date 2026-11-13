<?php
/**
 * Vista de Matriz de Normas y Versiones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Matriz de Normas";
$currentPage = 'normas';
$modudo_idx = 3;
$pagina_idx = 313; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-book-open me-2 text-primary"></i>Matriz de Normas y Versiones
                    </h3>
                    <small class="text-muted">Acervo jurídico, convenios internacionales, políticas corporativas y relaciones normativas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Matriz de Normas</li>
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
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Norma</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Organismo</label>
                            <select id="filtroOrganismo" class="form-select form-select-sm">
                                <option value="">-- Todos los organismos --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Nivel de Alcance</label>
                            <select id="filtroAmbito" class="form-select form-select-sm">
                                <option value="">-- Todos los niveles --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="" selected>-- Todos los estados --</option>
                                <option value="2045">Borrador</option>
                                <option value="2046">Pendiente de Aprobación</option>
                                <option value="2047">Confirmada / Vigente</option>
                                <option value="2048">Derogada / Inactiva</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Confidencial</label>
                            <select id="filtroConfidencial" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <option value="1">Solo Confidenciales</option>
                                <option value="0">Públicas / Abiertas</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-norma" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarNormas" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal de Normas -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaNormas" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">ID</th>
                                    <th style="width: 130px;">Código</th>
                                    <th>Título y Descripción</th>
                                    <th style="width: 140px;">Tipo / Jerarquía</th>
                                    <th style="width: 160px;">Organismo</th>
                                    <th style="width: 160px;">Alcance / Destino</th>
                                    <th style="width: 80px;" class="text-center">Versión</th>
                                    <th style="width: 90px;" class="text-center">Desde</th>
                                    <th style="width: 100px;" class="text-center">Estado</th>
                                    <th style="width: 130px;" class="text-center">Acciones</th>
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

<!-- Modal: Alta y Edición de Norma -->
<div class="modal fade" id="modalNorma" tabindex="-1" aria-labelledby="modalNormaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formNorma" novalidate>
                <input type="hidden" id="norma_id" name="norma_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalNormaLabel">
                        <i class="fas fa-book me-1"></i>Registrar Norma Jurídica o Corporativa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Código Oficial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="norma_codigo" name="codigo" required maxlength="80" placeholder="Ej: LEY-19587, ISO-14001">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Tipo de Norma <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="norma_tipo_id" name="norma_tipo_id" required>
                                <option value="">-- Seleccionar Tipo --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Jerarquía Legal</label>
                            <select class="form-select form-select-sm" id="norma_jerarquia_id" name="norma_jerarquia_id">
                                <option value="">-- Sin jerarquía fijada --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Título Oficial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="norma_titulo" name="titulo" required maxlength="255" placeholder="Nombre oficial o denominación reglamentaria">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Organismo Emisor</label>
                            <select class="form-select form-select-sm" id="norma_organismo_id" name="organismo_id">
                                <option value="">-- Sin organismo asignado --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Descripción / Resumen de Requisitos</label>
                        <textarea class="form-control form-control-sm" id="norma_descripcion" name="descripcion" rows="2" placeholder="Resumen del objeto regulado o alcance temático..."></textarea>
                    </div>

                    <!-- Idioma y traducción opcional -->
                    <div class="row g-2 mb-2 p-2 bg-light rounded border">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Idioma Original</label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="norma_idioma_original" name="idioma_original" value="ES" maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1">Título Traducido (Opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="norma_titulo_traducido" name="titulo_traducido" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1">Idioma Traducido</label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="norma_idioma_traducido" name="idioma_traducido" maxlength="10" placeholder="Ej: EN">
                        </div>
                    </div>

                    <!-- Ámbito de Aplicación Único (ck_nor_ambito) -->
                    <div class="p-2 border rounded mb-2" id="bloqueAmbitoSelector">
                        <label class="form-label form-label-sm fw-semibold text-primary">
                            <i class="fas fa-sitemap me-1"></i>Ámbito y Alcance de Aplicación <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-4 mb-2">
                                <select class="form-select form-select-sm" id="norma_ambito_tipo_id" name="ambito_tipo_id" required>
                                    <option value="">-- Nivel de Alcance --</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-2">
                                <div class="bloque-destino" id="destGrupo" style="display: none;">
                                    <select class="form-select form-select-sm" id="norma_grupo_id" name="grupo_id">
                                        <option value="">-- Seleccionar Grupo --</option>
                                    </select>
                                </div>
                                <div class="bloque-destino" id="destRegion" style="display: none;">
                                    <select class="form-select form-select-sm" id="norma_region_grupo_id" name="region_grupo_id">
                                        <option value="">-- Seleccionar Región --</option>
                                    </select>
                                </div>
                                <div class="bloque-destino" id="destEmpresa" style="display: none;">
                                    <select class="form-select form-select-sm" id="norma_empresa_id" name="empresa_id">
                                        <option value="">-- Seleccionar Empresa --</option>
                                    </select>
                                </div>
                                <div class="bloque-destino" id="destTerritorio" style="display: none;">
                                    <select class="form-select form-select-sm" id="norma_territorio_id" name="territorio_id">
                                        <option value="">-- Seleccionar Territorio / País --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opciones especiales y vigencias -->
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="norma_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="norma_vigente_hasta" name="vigente_hasta">
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="norma_requiere_local" name="requiere_norma_local" value="1">
                                <label class="form-check-label form-label-sm" for="norma_requiere_local">¿Requiere Ley Local?</label>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="norma_confidencial" name="confidencial" value="1">
                                <label class="form-check-label form-label-sm text-danger fw-semibold" for="norma_confidencial">¿Confidencial?</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarNorma">
                        <i class="fas fa-save me-1"></i>Guardar Norma
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Gestión de Versiones Documentales -->
<div class="modal fade" id="modalVersiones" tabindex="-1" aria-labelledby="modalVersionesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-dark py-2">
                <h5 class="modal-title fs-6" id="modalVersionesLabel">
                    <i class="fas fa-code-branch me-1"></i>Versiones de la Norma: <span id="lblNormaCodigoVersiones" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Formulario nueva versión -->
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-1 bg-light">
                        <span class="fs-7 fw-semibold"><i class="fas fa-plus me-1"></i>Cargar Nueva Versión (Caso C02)</span>
                    </div>
                    <div class="card-body p-2">
                        <form id="formNuevaVersion" class="row g-2 align-items-end" novalidate>
                            <input type="hidden" id="ver_norma_id" name="norma_id">
                            <div class="col-md-2 col-4">
                                <label class="form-label form-label-sm mb-0">Versión *</label>
                                <input type="text" class="form-control form-control-sm" id="ver_version" name="version" required placeholder="Ej: 2.0">
                            </div>
                            <div class="col-md-3 col-4">
                                <label class="form-label form-label-sm mb-0">Publicación</label>
                                <input type="date" class="form-control form-control-sm" id="ver_fecha_publicacion" name="fecha_publicacion">
                            </div>
                            <div class="col-md-3 col-4">
                                <label class="form-label form-label-sm mb-0">Vigente Desde *</label>
                                <input type="date" class="form-control form-control-sm" id="ver_vigente_desde" name="vigente_desde" required>
                            </div>
                            <div class="col-md-2 col-6">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" id="ver_activar" name="activar_inmediata" value="1" checked>
                                    <label class="form-check-label form-label-sm" for="ver_activar">Activar</label>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 text-end">
                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="fas fa-upload me-1"></i>Subir
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Versiones -->
                <div class="table-responsive">
                    <table id="tablaVersiones" class="table table-sm table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">ID</th>
                                <th style="width: 80px;">Versión</th>
                                <th style="width: 100px;" class="text-center">Publicación</th>
                                <th style="width: 100px;" class="text-center">Desde</th>
                                <th style="width: 80px;" class="text-center">Activa</th>
                                <th>Hash de Integridad (SHA-256)</th>
                                <th style="width: 90px;" class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="bodyVersiones"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Gestión de Relaciones Inter-Normas -->
<div class="modal fade" id="modalRelaciones" tabindex="-1" aria-labelledby="modalRelacionesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title fs-6" id="modalRelacionesLabel">
                    <i class="fas fa-project-diagram me-1"></i>Relaciones de la Norma: <span id="lblNormaCodigoRelaciones" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Formulario nuevo vínculo -->
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-1 bg-light">
                        <span class="fs-7 fw-semibold"><i class="fas fa-link me-1"></i>Vincular Relación con otra Norma (Caso C18)</span>
                    </div>
                    <div class="card-body p-2">
                        <form id="formNuevaRelacion" novalidate>
                            <input type="hidden" id="rel_norma_origen_id" name="norma_origen_id">
                            <div class="row g-2 mb-2">
                                <div class="col-md-5">
                                    <label class="form-label form-label-sm mb-0">Tipo de Relación *</label>
                                    <select class="form-select form-select-sm" id="rel_tipo_id" name="norma_relacion_tipo_id" required>
                                        <option value="">-- Seleccionar Tipo de Vínculo --</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label form-label-sm mb-0">Norma Destino *</label>
                                    <select class="form-select form-select-sm" id="rel_norma_destino_id" name="norma_destino_id" required>
                                        <option value="">-- Seleccionar Norma Destino --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label form-label-sm mb-0">Descripción / Artículo afectado (Opcional)</label>
                                    <input type="text" class="form-control form-control-sm" id="rel_descripcion" name="descripcion" placeholder="Ej: Modifica los Art. 12 y 15 sobre descansos...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label form-label-sm mb-0">Vigente Desde *</label>
                                    <input type="date" class="form-control form-control-sm" id="rel_vigente_desde" name="vigente_desde" required>
                                </div>
                                <div class="col-md-3 text-end">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                        <i class="fas fa-link me-1"></i>Vincular
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Relaciones -->
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;" class="text-center">Sentido</th>
                                <th style="width: 140px;">Vínculo</th>
                                <th>Norma Vinculada</th>
                                <th>Detalle / Artículos</th>
                                <th style="width: 90px;" class="text-center">Desde</th>
                                <th style="width: 60px;" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="bodyRelaciones"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="normas.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
