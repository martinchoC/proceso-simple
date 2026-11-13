<?php
/**
 * Vista de Conflictos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_conflictos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Conflictos Normativos";
$currentPage = 'normas_conflictos';
$modudo_idx = 3;
$pagina_idx = 314; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Conflictos e Incompatibilidades Normativas
                    </h3>
                    <small class="text-muted">Detección, escalado y dictamen legal de normas contradictorias (Caso C19)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Conflictos Normativos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros -->
            <div class="card card-outline card-warning mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado del Conflicto</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="0">-- Todos los estados --</option>
                                <option value="2084">Abierto / Detectado</option>
                                <option value="2085">En Análisis Legal</option>
                                <option value="2086">Resuelto / Con Dictamen</option>
                            </select>
                        </div>
                        <div class="col-md-9 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-conflicto" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Conflictos -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaConflictos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">ID</th>
                                    <th>Norma A (Exige / Prohíbe)</th>
                                    <th>Norma B (Incompatible)</th>
                                    <th>Descripción de la Contradicción</th>
                                    <th style="width: 140px;">Escalado a</th>
                                    <th>Dictamen / Resolución Legal</th>
                                    <th style="width: 110px;" class="text-center">Estado</th>
                                    <th style="width: 80px;" class="text-center">Acciones</th>
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

<!-- Modal: Registrar Conflicto -->
<div class="modal fade" id="modalConflicto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formConflicto" novalidate>
                <div class="modal-header bg-warning text-dark py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-exclamation-triangle me-1"></i>Declarar Incompatibilidad Normativa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Norma A (Primera exigencia) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="conf_norma_a" name="norma_a_id" required>
                                <option value="">-- Seleccionar Norma A --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Norma B (Norma incompatible) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="conf_norma_b" name="norma_b_id" required>
                                <option value="">-- Seleccionar Norma B --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Descripción del Conflicto Legal o Técnico <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="conf_descripcion" name="descripcion" rows="3" required placeholder="Explique por qué una norma prohíbe lo que la otra exige, o la imposibilidad material de cumplimiento simultáneo..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Escalar inmediatamente a Abogado (Opcional)</label>
                        <select class="form-select form-select-sm" id="conf_escalar_a" name="escalar_a">
                            <option value="">-- Dejar abierto sin asignación aún --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-save me-1"></i>Registrar Conflicto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Escalar a Legales -->
<div class="modal fade" id="modalEscalar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEscalar" novalidate>
                <input type="hidden" id="esc_conflicto_id" name="norma_conflicto_id">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-user-tie me-1"></i>Escalar al Área Legal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Abogado / Responsable Legal <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="esc_abogado_id" name="abogado_id" required>
                            <option value="">-- Seleccionar Abogado --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i>Escalar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Dictaminar / Resolver Conflicto -->
<div class="modal fade" id="modalResolver" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formResolver" novalidate>
                <input type="hidden" id="res_conflicto_id" name="norma_conflicto_id">
                <div class="modal-header bg-success text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-gavel me-1"></i>Emitir Dictamen y Resolución Legal (Caso C19)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold">Fundamento y Dictamen Resolutivo <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="res_decision" name="decision" rows="4" required placeholder="Indique la norma de prevalencia por jerarquía constitucional o especialidad, y la instrucción vinculante para los ciclos..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check-circle me-1"></i>Confirmar Dictamen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="normas_conflictos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
