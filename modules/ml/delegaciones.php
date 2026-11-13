<?php
/**
 * Vista de Delegaciones y Suplencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/delegaciones.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Delegaciones y Suplencias";
$currentPage = 'delegaciones';
$modudo_idx = 3;
$pagina_idx = 310; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-user-friends me-2 text-primary"></i>Delegaciones y Suplencias
                    </h3>
                    <small class="text-muted">Gestión de ausencias temporales, licencias y reemplazos de aprobadores</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Delegaciones</li>
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
                            <label class="form-label form-label-sm mb-1 text-muted">Usuario Titular</label>
                            <select id="filtroTitular" class="form-select form-select-sm">
                                <option value="">-- Todos los titulares --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Usuario Suplente</label>
                            <select id="filtroSuplente" class="form-select form-select-sm">
                                <option value="">-- Todos los suplentes --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <option value="ACTIVA" selected>Solo Activas</option>
                                <option value="FUTURA">Futuras / Programadas</option>
                                <option value="FINALIZADA">Finalizadas / Históricas</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6 text-md-end mt-3 mt-md-auto">
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
                        <table id="tablaDelegaciones" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">ID</th>
                                    <th>Usuario Titular</th>
                                    <th>Usuario Suplente</th>
                                    <th>Perfil Delegado</th>
                                    <th>Motivo de Ausencia / Suplencia</th>
                                    <th style="width: 120px;" class="text-center">Vigente Desde</th>
                                    <th style="width: 120px;" class="text-center">Vigente Hasta</th>
                                    <th style="width: 100px;" class="text-center">Estado</th>
                                    <th style="width: 90px;" class="text-center">Acciones</th>
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

<!-- Modal: Registrar Delegación -->
<div class="modal fade" id="modalDelegacion" tabindex="-1" aria-labelledby="modalDelegacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formDelegacion" novalidate>
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalDelegacionLabel">
                        <i class="fas fa-user-clock me-1"></i>Registrar Suplencia / Delegación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_usuario_id" class="form-label form-label-sm fw-semibold">Usuario Titular (Ausente) <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_usuario_id" name="usuario_id" required>
                            <option value="">-- Seleccionar Titular --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione el usuario titular.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_usuario_suplente_id" class="form-label form-label-sm fw-semibold">Usuario Suplente (Reemplazante) <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_usuario_suplente_id" name="usuario_suplente_id" required>
                            <option value="">-- Seleccionar Suplente --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione el usuario que actuará como suplente.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_empresa_perfil_id" class="form-label form-label-sm fw-semibold">Perfil Específico Delegado</label>
                        <select class="form-select form-select-sm" id="modal_empresa_perfil_id" name="empresa_perfil_id">
                            <option value="">-- Todos los perfiles del titular en Matriz Legal --</option>
                        </select>
                        <small class="text-muted fs-8">Deje en blanco para delegar todos los roles del titular</small>
                    </div>
                    <div class="mb-3">
                        <label for="modal_motivo" class="form-label form-label-sm fw-semibold">Motivo</label>
                        <input type="text" class="form-control form-control-sm" id="modal_motivo" name="motivo" maxlength="255" placeholder="Ej: Vacaciones anuales, Licencia médica, Misión técnica...">
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label for="modal_vigente_desde" class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="modal_vigente_desde" name="vigente_desde" required>
                            <div class="invalid-feedback">Indique fecha de inicio.</div>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="modal_vigente_hasta" class="form-label form-label-sm fw-semibold">Vigente Hasta <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="modal_vigente_hasta" name="vigente_hasta" required>
                            <div class="invalid-feedback">Indique fecha de finalización.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarDelegacion">
                        <i class="fas fa-save me-1"></i>Registrar Suplencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="delegaciones.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
