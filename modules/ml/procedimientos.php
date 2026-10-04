<?php
/**
 * Vista de Procedimientos y Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Procedimientos y Soporte";
$currentPage = 'procedimientos';
$modudo_idx = 3;
$pagina_idx = 315; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-alt me-2 text-primary"></i>Procedimientos y Soporte Operativo
                    </h3>
                    <small class="text-muted">Manuales, directivas técnicas e instrucciones paso a paso que guían la ejecución de obligaciones</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Procedimientos</li>
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
                            <label class="form-label form-label-sm mb-1 text-muted">Estado del Procedimiento</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="0">-- Todos los estados --</option>
                                <option value="2053">Borrador</option>
                                <option value="2054">Pendiente de Aprobación</option>
                                <option value="2055" selected>Confirmado / Aprobado</option>
                                <option value="2056">Eliminado / Obsoleto</option>
                            </select>
                        </div>
                        <div class="col-md-9 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-procedimiento" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Procedimientos -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaProcedimientos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">ID</th>
                                    <th style="width: 130px;">Código</th>
                                    <th>Nombre del Procedimiento</th>
                                    <th>Empresa Destino</th>
                                    <th>Norma Jurídica Asociada</th>
                                    <th style="width: 80px;" class="text-center">Versión</th>
                                    <th style="width: 90px;" class="text-center">Confidencial</th>
                                    <th style="width: 100px;" class="text-center">Estado</th>
                                    <th style="width: 110px;" class="text-center">Acciones</th>
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

<!-- Modal: Alta y Edición de Procedimiento -->
<div class="modal fade" id="modalProcedimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formProcedimiento" novalidate>
                <input type="hidden" id="proc_id" name="procedimiento_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalProcTitle"><i class="fas fa-file-alt me-1"></i>Registrar Procedimiento de Soporte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="proc_codigo" name="codigo" required maxlength="80" placeholder="Ej: PRO-SEG-01">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label form-label-sm fw-semibold">Nombre del Procedimiento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="proc_nombre" name="nombre" required maxlength="255">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Empresa Destino</label>
                            <select class="form-select form-select-sm" id="proc_empresa_id" name="empresa_id">
                                <option value="">-- Aplica a todo el Grupo --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Norma de Origen Vinculada</label>
                            <select class="form-select form-select-sm" id="proc_norma_id" name="norma_id">
                                <option value="">-- Sin norma asociada (Soporte interno) --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Descripción / Alcance</label>
                        <textarea class="form-control form-control-sm" id="proc_descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="proc_confidencial" name="confidencial" value="1">
                        <label class="form-check-label form-label-sm text-danger fw-semibold" for="proc_confidencial">¿Procedimiento Confidencial?</label>
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

<!-- Modal: Versiones e Instrucciones de Trabajo -->
<div class="modal fade" id="modalVersionesProc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-tasks me-1"></i>Versiones e Instrucciones: <span id="lblProcCodigo" class="fw-bold"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row">
                    <!-- Columna Izquierda: Versiones -->
                    <div class="col-md-5 border-end">
                        <h6 class="fw-bold text-primary mb-2"><i class="fas fa-code-branch me-1"></i>Versiones del Manual</h6>
                        <form id="formNuevaVersionProc" class="row g-2 mb-3 bg-light p-2 rounded border" novalidate>
                            <input type="hidden" id="verproc_id" name="procedimiento_id">
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" id="verproc_version" name="version" required placeholder="Versión">
                            </div>
                            <div class="col-5">
                                <input type="date" class="form-control form-control-sm" id="verproc_desde" name="vigente_desde" required>
                            </div>
                            <div class="col-3 text-end">
                                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-plus"></i></button>
                            </div>
                        </form>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Versión</th>
                                        <th>Desde</th>
                                        <th>Estado</th>
                                        <th class="text-center">Seleccionar</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyVersionesProc"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Columna Derecha: Instrucciones de Trabajo -->
                    <div class="col-md-7">
                        <h6 class="fw-bold text-success mb-2">
                            <i class="fas fa-list-ol me-1"></i>Instrucciones Paso a Paso: <span id="lblVersionSeleccionada" class="badge bg-secondary">-</span>
                        </h6>
                        <form id="formNuevaInstruccion" class="row g-2 mb-3 bg-light p-2 rounded border" novalidate style="display: none;">
                            <input type="hidden" id="ins_version_id" name="procedimiento_version_id">
                            <div class="col-2">
                                <input type="number" class="form-control form-control-sm" id="ins_orden" name="orden" value="1" min="1" required placeholder="N°">
                            </div>
                            <div class="col-7">
                                <input type="text" class="form-control form-control-sm" id="ins_titulo" name="titulo" required placeholder="Título del paso...">
                            </div>
                            <div class="col-3 text-end">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>Paso</button>
                            </div>
                            <div class="col-12 mt-1">
                                <input type="text" class="form-control form-control-sm" id="ins_descripcion" name="descripcion" placeholder="Detalle técnico u operativo del paso...">
                            </div>
                        </form>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">N°</th>
                                        <th>Instrucción / Paso Operativo</th>
                                        <th style="width: 50px;" class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyInstrucciones">
                                    <tr><td colspan="3" class="text-center text-muted py-2">Seleccione una versión a la izquierda</td></tr>
                                </tbody>
                            </table>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="procedimientos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
