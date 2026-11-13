<?php
/**
 * Vista de Posiciones y Organigrama Jerárquico (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/posiciones.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Posiciones y Organigrama Jerárquico";
$currentPage = 'posiciones';
$modudo_idx = 3;
$pagina_idx = 317; // Registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    /* Estilos premium para Organigrama y Jerarquías */
    .kpi-card {
        border-radius: 0.75rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    }
    .organigrama-container {
        overflow-x: auto;
        padding: 1.5rem 0.5rem;
        background: #f8fafc;
        border-radius: 0.75rem;
        border: 1px dashed #cbd5e1;
        min-height: 480px;
    }
    .org-tree, .org-tree ul, .org-tree li {
        list-style-type: none;
        margin: 0;
        padding: 0;
        position: relative;
    }
    .org-tree {
        display: flex;
        justify-content: center;
    }
    .org-tree ul {
        display: flex;
        padding-top: 24px;
        position: relative;
        transition: all 0.3s;
    }
    .org-tree li {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0 12px;
        position: relative;
    }
    /* Conectores */
    .org-tree li::before, .org-tree li::after {
        content: '';
        position: absolute;
        top: 0;
        right: 50%;
        border-top: 2px solid #94a3b8;
        width: 50%;
        height: 24px;
    }
    .org-tree li::after {
        right: auto;
        left: 50%;
        border-left: 2px solid #94a3b8;
    }
    .org-tree li:only-child::after, .org-tree li:only-child::before {
        display: none;
    }
    .org-tree li:only-child {
        padding-top: 0;
    }
    .org-tree li:first-child::before, .org-tree li:last-child::after {
        border: 0 none;
    }
    .org-tree li:last-child::before {
        border-right: 2px solid #94a3b8;
        border-radius: 0 8px 0 0;
    }
    .org-tree li:first-child::after {
        border-radius: 8px 0 0 0;
    }
    .org-tree ul ul::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        border-left: 2px solid #94a3b8;
        width: 0;
        height: 24px;
    }
    /* Tarjeta de Nodo de Posición */
    .org-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.6rem;
        padding: 0.75rem 0.9rem;
        min-width: 210px;
        max-width: 240px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        transition: all 0.2s ease-in-out;
        text-align: center;
        position: relative;
        z-index: 2;
    }
    .org-card:hover {
        border-color: #0d6efd;
        box-shadow: 0 6px 14px rgba(13,110,253,0.18);
        transform: translateY(-3px);
    }
    .org-card.root-card {
        border-top: 4px solid #0d6efd;
        background: #f0f7ff;
    }
    .org-card.manager-card {
        border-top: 4px solid #0dcaf0;
    }
    .org-card.staff-card {
        border-top: 4px solid #198754;
    }
    .badge-puesto-codigo {
        font-family: monospace;
        letter-spacing: 0.5px;
        font-size: 0.75rem;
    }
</style>

<main class="app-main">
    <!-- Encabezado de Página -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0">
                        <i class="fas fa-sitemap me-2 text-primary"></i>Posiciones y Organigrama Jerárquico
                    </h3>
                    <small class="text-muted">
                        Estructura de cargos, árbol jerárquico de supervisión y asignación de ocupantes por puesto
                    </small>
                </div>
                <div class="col-sm-5 text-sm-end mt-2 mt-sm-0">
                    <button type="button" class="btn btn-sm btn-outline-primary me-1" id="btnConsultarSupervisor" title="Buscar supervisor de un usuario">
                        <i class="fas fa-user-check me-1"></i>Consultar Supervisor
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnNuevaPosicion">
                        <i class="fas fa-plus me-1"></i>Nueva Posición
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Indicadores KPI Rápidos -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card shadow-sm border-0 border-start border-primary border-4 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold">Total Posiciones</small>
                                <h3 class="mb-0 fw-bold text-primary" id="kpiTotalPosiciones">-</h3>
                            </div>
                            <div class="fs-2 text-primary opacity-50"><i class="fas fa-briefcase"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card shadow-sm border-0 border-start border-success border-4 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold">Puestos Cubiertos</small>
                                <h3 class="mb-0 fw-bold text-success" id="kpiCubiertas">-</h3>
                            </div>
                            <div class="fs-2 text-success opacity-50"><i class="fas fa-user-tie"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card shadow-sm border-0 border-start border-warning border-4 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold">Vacantes</small>
                                <h3 class="mb-0 fw-bold text-warning" id="kpiVacantes">-</h3>
                            </div>
                            <div class="fs-2 text-warning opacity-50"><i class="fas fa-user-slash"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card kpi-card shadow-sm border-0 border-start border-info border-4 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold">Áreas / Deptos</small>
                                <h3 class="mb-0 fw-bold text-info" id="kpiAreas">-</h3>
                            </div>
                            <div class="fs-2 text-info opacity-50"><i class="fas fa-building"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel con Solapas / Vistas -->
            <div class="card shadow-sm card-outline card-primary">
                <div class="card-header p-2 d-flex align-items-center justify-content-between">
                    <ul class="nav nav-pills" id="tabsPosiciones" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active py-1 px-3" id="tab-lista" data-bs-toggle="pill" data-bs-target="#panel-lista" type="button" role="tab">
                                <i class="fas fa-list me-1"></i>Catálogo de Posiciones
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-1 px-3" id="tab-organigrama" data-bs-toggle="pill" data-bs-target="#panel-organigrama" type="button" role="tab">
                                <i class="fas fa-project-diagram me-1"></i>Organigrama Visual
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-1 px-3" id="tab-supervision" data-bs-toggle="pill" data-bs-target="#panel-supervision" type="button" role="tab">
                                <i class="fas fa-users-cog me-1"></i>Matriz de Supervisión Directa
                            </button>
                        </li>
                    </ul>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarDatos" title="Actualizar datos">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body p-3">
                    <div class="tab-content" id="tabsPosicionesContent">
                        <!-- SOLAPA 1: TABLA PRINCIPAL DE POSICIONES -->
                        <div class="tab-pane fade show active" id="panel-lista" role="tabpanel">
                            <!-- Filtros rápidos -->
                            <div class="row g-2 align-items-center mb-3">
                                <div class="col-md-4 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Filtrar por Área / Departamento</label>
                                    <select id="filtroArea" class="form-select form-select-sm">
                                        <option value="">-- Todas las áreas --</option>
                                    </select>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                                    <select id="filtroEstado" class="form-select form-select-sm">
                                        <option value="">Todos los estados</option>
                                        <option value="1" selected>Solo Activos</option>
                                        <option value="2">Inactivos</option>
                                    </select>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="tablaPosiciones" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;">Código</th>
                                            <th>Posición / Cargo</th>
                                            <th>Área / Departamento</th>
                                            <th>Posición Superior (Reporta a)</th>
                                            <th>Ocupante Actual</th>
                                            <th>Supervisor Directo</th>
                                            <th style="width: 80px;" class="text-center">Estado</th>
                                            <th style="width: 120px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- SOLAPA 2: ORGANIGRAMA VISUAL JERÁRQUICO -->
                        <div class="tab-pane fade" id="panel-organigrama" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Visualización jerárquica del organigrama. Pasa el cursor sobre cada puesto para ver más opciones.
                                </small>
                                <div>
                                    <button class="btn btn-xs btn-outline-secondary" id="btnZoomOrganigrama" title="Ajustar vista">
                                        <i class="fas fa-expand-arrows-alt me-1"></i>Centrar
                                    </button>
                                </div>
                            </div>
                            <div class="organigrama-container" id="contenedorOrganigrama">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Cargando organigrama...
                                </div>
                            </div>
                        </div>

                        <!-- SOLAPA 3: MATRIZ DE SUPERVISIÓN POR USUARIO -->
                        <div class="tab-pane fade" id="panel-supervision" role="tabpanel">
                            <div class="alert alert-light border border-info d-flex align-items-center py-2 px-3 mb-3">
                                <i class="fas fa-shield-alt text-info fs-4 me-3"></i>
                                <div>
                                    <strong>Cuadro de Mando y Supervisores por Empleado</strong><br>
                                    <small class="text-muted">
                                        Esta tabla resume la subordinación directa de cada empleado en el sistema. Es la base para escalar aprobaciones (Rol A de la Matriz RACI) y delegaciones automáticas.
                                    </small>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaSupervision" class="table table-sm table-bordered table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Empleado / Usuario</th>
                                            <th>Cargo Asignado</th>
                                            <th>Área</th>
                                            <th>Puesto Superior Jerárquico</th>
                                            <th class="bg-primary bg-opacity-10">Supervisor Directo</th>
                                            <th>Email de Notificaciones</th>
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

<!-- MODAL: CREAR / EDITAR POSICIÓN -->
<div class="modal fade" id="modalPosicion" tabindex="-1" aria-labelledby="modalPosicionTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2 bg-primary text-white">
                <h5 class="modal-title fs-6" id="modalPosicionTitulo">
                    <i class="fas fa-briefcase me-2"></i>Nueva Posición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formPosicion">
                <input type="hidden" id="pos_id" name="posicion_id" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-semibold">Código Único <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="pos_codigo" name="codigo" required placeholder="Ej: JEF-CONT" maxlength="30">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label form-label-sm fw-semibold">Nombre del Cargo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="pos_nombre" name="nombre" required placeholder="Ej: Jefe Contable y Cumplimiento" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold">Área / Departamento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="pos_area" name="area_departamento" required placeholder="Ej: Administración y Finanzas" list="listaAreasDatalist">
                            <datalist id="listaAreasDatalist"></datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold">Posición Superior (Reporta a)</label>
                            <select class="form-select form-select-sm" id="pos_superior_id" name="posicion_superior_id">
                                <option value="">-- Ninguno (Cúspide de la empresa) --</option>
                            </select>
                            <small class="text-muted d-block mt-1">El ocupante de este puesto superior será el supervisor directo de quien ocupe este cargo.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold">Descripción de Responsabilidades</label>
                            <textarea class="form-control form-control-sm" id="pos_descripcion" name="descripcion" rows="2" placeholder="Alcance funcional del puesto..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarPosicion">
                        <i class="fas fa-save me-1"></i>Guardar Posición
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ASIGNAR USUARIO A POSICIÓN -->
<div class="modal fade" id="modalAsignarUsuario" tabindex="-1" aria-labelledby="modalAsignarUsuarioTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2 bg-success text-white">
                <h5 class="modal-title fs-6" id="modalAsignarUsuarioTitulo">
                    <i class="fas fa-user-plus me-2"></i>Asignar Usuario a Posición
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarUsuario">
                <input type="hidden" id="asig_posicion_id" name="posicion_id" value="0">
                <div class="modal-body">
                    <div class="alert alert-light border py-2 px-3 mb-3">
                        <div class="text-muted small">Posición a Ocupar:</div>
                        <strong class="fs-6 text-primary" id="asig_posicion_nombre">-</strong>
                        <div class="text-muted small mt-1" id="asig_posicion_area">-</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-semibold">Seleccionar Usuario / Empleado <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="asig_usuario_id" name="usuario_id" required>
                                <option value="">-- Seleccionar usuario --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Carácter de Asignación</label>
                            <select class="form-select form-select-sm" id="asig_es_titular" name="es_titular">
                                <option value="1" selected>Titular Oficial</option>
                                <option value="0">Adjunto / Co-responsable</option>
                            </select>
                            <small class="text-muted d-block mt-1">El titular oficial es quien ejerce la supervisión directa.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="asig_vigente_desde" name="vigente_desde" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success" id="btnConfirmarAsignacion">
                        <i class="fas fa-check me-1"></i>Asignar al Puesto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: HISTORIAL DE OCUPANTES Y DETALLE -->
<div class="modal fade" id="modalHistorialPosicion" tabindex="-1" aria-labelledby="modalHistorialTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2 bg-secondary text-white">
                <h5 class="modal-title fs-6" id="modalHistorialTitulo">
                    <i class="fas fa-history me-2"></i>Historial del Puesto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0 text-primary fw-bold" id="hist_pos_nombre">-</h5>
                        <span class="badge bg-secondary badge-puesto-codigo" id="hist_pos_codigo">-</span>
                        <span class="text-muted ms-2" id="hist_pos_area">-</span>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Reporta jerárquicamente a:</small>
                        <strong class="text-dark" id="hist_pos_superior">-</strong>
                    </div>
                </div>

                <h6 class="fw-bold border-bottom pb-1 text-muted">Historial de Ocupantes</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-striped align-middle mb-0" id="tablaHistorialOcupantes">
                        <thead class="table-light">
                            <tr>
                                <th>Usuario / Empleado</th>
                                <th>Email</th>
                                <th style="width: 100px;">Carácter</th>
                                <th style="width: 110px;">Desde</th>
                                <th style="width: 110px;">Hasta</th>
                                <th style="width: 90px;" class="text-center">Estado</th>
                                <th style="width: 90px;" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <h6 class="fw-bold border-bottom pb-1 text-muted">Puestos Subordinados Directos (Dependencias)</h6>
                <div id="listaSubordinadosDirectos" class="mb-2"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CONSULTA RÁPIDA DE SUPERVISOR -->
<div class="modal fade" id="modalConsultaSupervisor" tabindex="-1" aria-labelledby="modalConsultaSupervisorTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2 bg-info text-dark">
                <h5 class="modal-title fs-6" id="modalConsultaSupervisorTitulo">
                    <i class="fas fa-search me-2"></i>Consultar Supervisor de Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label form-label-sm fw-semibold">Seleccionar Empleado</label>
                    <select class="form-select form-select-sm" id="selectConsultaUsuario">
                        <option value="">-- Seleccionar usuario --</option>
                    </select>
                </div>

                <div id="resultadoSupervisorCard" class="card shadow-sm border border-primary p-3 bg-light d-none">
                    <div class="text-center mb-3">
                        <div class="fs-1 text-primary"><i class="fas fa-user-tie"></i></div>
                        <h5 class="mb-0 fw-bold" id="res_empleado_nombre">-</h5>
                        <span class="badge bg-secondary badge-puesto-codigo" id="res_empleado_puesto">-</span>
                        <div class="text-muted small" id="res_empleado_area">-</div>
                    </div>

                    <div class="border-top pt-2">
                        <div class="d-flex align-items-center bg-white p-3 rounded border">
                            <div class="fs-2 text-success me-3"><i class="fas fa-arrow-up"></i></div>
                            <div>
                                <small class="text-muted d-block text-uppercase fw-semibold">Supervisor Directo Inmediato:</small>
                                <h6 class="mb-0 fw-bold text-dark" id="res_supervisor_nombre">-</h6>
                                <span class="badge bg-primary bg-opacity-25 text-primary" id="res_supervisor_puesto">-</span>
                                <div class="text-muted small mt-1" id="res_supervisor_email">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="posiciones.js"></script>
