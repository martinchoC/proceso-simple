<?php
/**
 * Vista de Entidades Sujetas a Control (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Entidades Sujetas a Control";
$currentPage = 'entidades';
$modudo_idx = 3;
$pagina_idx = 304; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-boxes me-2 text-primary"></i>Entidades Sujetas a Control
                    </h3>
                    <small class="text-muted">Activos, plantas, unidades operativas, buques y dependencias corporativas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Entidades</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtros de búsqueda -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Empresa Titular</label>
                            <select id="filtroEmpresa" class="form-select form-select-sm">
                                <option value="">-- Todas las empresas --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Entidad</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Fija / Móvil</label>
                            <select id="filtroMovil" class="form-select form-select-sm">
                                <option value="-1">Todas</option>
                                <option value="0">Fijas (Plantas/Sedes)</option>
                                <option value="1">Móviles (Buques/Flota)</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="1">Activas</option>
                                <option value="0">Todas</option>
                                <option value="2">Inactivas</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-12 text-md-end mt-3 mt-md-auto">
                            <div id="contenedor-boton-agregar" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaEntidades" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center">ID</th>
                                    <th style="width: 110px;">Código</th>
                                    <th>Nombre de la Entidad</th>
                                    <th style="width: 160px;">Tipo</th>
                                    <th>Empresa Titular Actual</th>
                                    <th>Ubicación Territorial Actual</th>
                                    <th>Entidad Superior / Padre</th>
                                    <th style="width: 90px;" class="text-center">Estado</th>
                                    <th style="width: 170px;" class="text-center">Acciones</th>
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

<!-- Modal: Agregar / Editar Entidad -->
<div class="modal fade" id="modalEntidad" tabindex="-1" aria-labelledby="modalEntidadLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formEntidad" novalidate>
                <input type="hidden" id="entidad_id" name="entidad_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalEntidadLabel">
                        <i class="fas fa-box me-1"></i> <span id="tituloModalEntidad">Nueva Entidad Sujeta a Control</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4 mb-3">
                            <label for="modal_codigo" class="form-label form-label-sm fw-semibold">Código Identificador <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase font-monospace" id="modal_codigo" name="codigo" maxlength="80" required placeholder="Ej: TERM-BA-01">
                            <div class="invalid-feedback">Indique un código identificador.</div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="modal_nombre" class="form-label form-label-sm fw-semibold">Nombre de la Entidad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_nombre" name="nombre" maxlength="200" required placeholder="Ej: Terminal Fluvial Dock Sud">
                            <div class="invalid-feedback">Indique el nombre descriptivo.</div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label for="modal_entidad_tipo_id" class="form-label form-label-sm fw-semibold">Tipo de Entidad <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="modal_entidad_tipo_id" name="entidad_tipo_id" required>
                                <option value="">-- Seleccionar Tipo --</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el tipo de entidad.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_entidad_padre_id" class="form-label form-label-sm fw-semibold">Entidad Superior / Contenedora (Opcional)</label>
                            <select class="form-select form-select-sm" id="modal_entidad_padre_id" name="entidad_padre_id">
                                <option value="">-- Ninguna (Entidad Raíz) --</option>
                            </select>
                            <small class="text-muted fs-8">Permite jerarquía de componentes (Ej: Tanque dentro de Terminal)</small>
                        </div>
                    </div>

                    <div class="row g-2" id="bloqueTitularidadAlta">
                        <div class="col-md-6 mb-3">
                            <label for="modal_empresa_titular_id" class="form-label form-label-sm fw-semibold">Empresa Titular Responsable <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="modal_empresa_titular_id" name="empresa_titular_id" required>
                                <option value="">-- Seleccionar Empresa --</option>
                            </select>
                            <div class="invalid-feedback">Seleccione la empresa titular.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_territorio_id" class="form-label form-label-sm fw-semibold">Ubicación Territorial / Base Operativa</label>
                            <select class="form-select form-select-sm" id="modal_territorio_id" name="territorio_id">
                                <option value="">-- Seleccionar Territorio --</option>
                            </select>
                            <small class="text-muted fs-8">País o localidad donde opera o radica</small>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label for="modal_vigente_desde" class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="modal_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_descripcion" class="form-label form-label-sm fw-semibold">Descripción / Observaciones</label>
                            <textarea class="form-control form-control-sm" id="modal_descripcion" name="descripcion" rows="2" placeholder="Detalles de operatividad, matrículas, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarEntidad">
                        <i class="fas fa-save me-1"></i>Guardar Entidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Transferir Titularidad (C16) -->
<div class="modal fade" id="modalTransferir" tabindex="-1" aria-labelledby="modalTransferirLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formTransferir" novalidate>
                <input type="hidden" id="transf_entidad_id" name="entidad_id" value="0">
                <div class="modal-header bg-warning text-dark py-2">
                    <h5 class="modal-title fs-6" id="modalTransferirLabel">
                        <i class="fas fa-exchange-alt me-1"></i>Transferir Titularidad de Entidad
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-muted small">Transfiera la titularidad legal de <strong id="transf_nombre_entidad" class="text-dark"></strong> hacia otra empresa del grupo, preservando el historial.</p>
                    <div class="mb-3">
                        <label for="transf_nueva_empresa_id" class="form-label form-label-sm fw-semibold">Nueva Empresa Titular <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="transf_nueva_empresa_id" name="nueva_empresa_id" required>
                            <option value="">-- Seleccionar Empresa Destino --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione la empresa destinataria.</div>
                    </div>
                    <div class="mb-3">
                        <label for="transf_fecha" class="form-label form-label-sm fw-semibold">Fecha Efectiva de Transferencia <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="transf_fecha" name="fecha_transferencia" required>
                        <div class="invalid-feedback">Indique la fecha de transferencia.</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark" id="btnEjecutarTransferencia">
                        <i class="fas fa-check me-1"></i>Transferir Titularidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Ubicación Base (C05 / C06) -->
<div class="modal fade" id="modalUbicacion" tabindex="-1" aria-labelledby="modalUbicacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formUbicacion" novalidate>
                <input type="hidden" id="ubi_entidad_id" name="entidad_id" value="0">
                <div class="modal-header bg-info text-white py-2">
                    <h5 class="modal-title fs-6" id="modalUbicacionLabel">
                        <i class="fas fa-map-marker-alt me-1"></i>Actualizar Ubicación Territorial
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-muted small">Actualice la base territorial de <strong id="ubi_nombre_entidad" class="text-dark"></strong>.</p>
                    <div class="mb-3">
                        <label for="ubi_nuevo_territorio_id" class="form-label form-label-sm fw-semibold">Nuevo Territorio / Jurisdicción <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="ubi_nuevo_territorio_id" name="nuevo_territorio_id" required>
                            <option value="">-- Seleccionar Territorio --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione la jurisdicción.</div>
                    </div>
                    <div class="mb-3">
                        <label for="ubi_fecha" class="form-label form-label-sm fw-semibold">Fecha de Traslado / Efectiva <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="ubi_fecha" name="fecha_traslado" required>
                        <div class="invalid-feedback">Indique la fecha de radicación.</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-info text-white" id="btnEjecutarUbicacion">
                        <i class="fas fa-save me-1"></i>Actualizar Ubicación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Historial de la Entidad -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="modalHistorialLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h5 class="modal-title fs-6" id="modalHistorialLabel">
                    <i class="fas fa-history me-1"></i>Historial de Trazabilidad: <span id="hist_nombre_entidad"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-building me-1"></i>Historial de Empresas Titulares</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered text-center align-middle" id="tablaHistorialEmpresas">
                        <thead class="table-light">
                            <tr>
                                <th>Empresa</th>
                                <th>CUIT</th>
                                <th>Rol</th>
                                <th>Vigente Desde</th>
                                <th>Vigente Hasta</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <h6 class="fw-bold text-info mb-2"><i class="fas fa-map-marker-alt me-1"></i>Historial de Sedes y Ubicaciones</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center align-middle" id="tablaHistorialUbicaciones">
                        <thead class="table-light">
                            <tr>
                                <th>Territorio</th>
                                <th>Nivel / Tipo</th>
                                <th>Vigente Desde</th>
                                <th>Vigente Hasta</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
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
<script src="entidades.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>