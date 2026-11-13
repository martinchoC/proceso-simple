<?php
/**
 * Vista de Accesos Confidenciales y Auditoría (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/accesos_confidenciales.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Accesos Confidenciales";
$currentPage = 'accesos_confidenciales';
$modudo_idx = 3;
$pagina_idx = 316; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-user-secret me-2 text-danger"></i>Accesos Confidenciales y Auditoría
                    </h3>
                    <small class="text-muted">Gobierno de información reservada y trazabilidad inmutable de consultas (Caso C33)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Accesos Confidenciales</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Pestañas -->
            <ul class="nav nav-tabs mb-3" id="tabConfidencial" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-autorizaciones-tab" data-bs-toggle="tab" data-bs-target="#tab-autorizaciones" type="button" role="tab" aria-controls="tab-autorizaciones" aria-selected="true">
                        <i class="fas fa-key me-1"></i>Autorizaciones de Acceso Reservado
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-auditoria-tab" data-bs-toggle="tab" data-bs-target="#tab-auditoria" type="button" role="tab" aria-controls="tab-auditoria" aria-selected="false">
                        <i class="fas fa-history me-1"></i>Log de Auditoría Inmutable (Caso C33)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="tabConfidencialContent">
                <!-- PESTAÑA 1: AUTORIZACIONES -->
                <div class="tab-pane fade show active" id="tab-autorizaciones" role="tabpanel">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row align-items-center">
                                <div class="col-sm-6">
                                    <span class="text-muted fw-semibold">Perfiles y ámbitos con permiso explícito para visualizar normas y procedimientos reservados</span>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <div id="btn-container-autorizar" class="d-inline-block me-1"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargar" data-tabla="autorizaciones" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaAutorizaciones" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">ID</th>
                                            <th>Objeto Confidencial</th>
                                            <th>Perfil Habilitado</th>
                                            <th>Alcance / Destino</th>
                                            <th style="width: 100px;" class="text-center">Desde</th>
                                            <th style="width: 100px;" class="text-center">Hasta</th>
                                            <th style="width: 80px;" class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 2: AUDITORÍA -->
                <div class="tab-pane fade" id="tab-auditoria" role="tabpanel">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light py-2">
                            <div class="row align-items-center">
                                <div class="col-sm-6">
                                    <span class="text-muted fw-semibold"><i class="fas fa-shield-alt text-danger me-1"></i>Registro inmutable de visualización y consulta de documentos confidenciales</span>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btnRecargar" data-tabla="auditoria" title="Refrescar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaAuditoria" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;" class="text-center">ID</th>
                                            <th style="width: 150px;">Fecha y Hora</th>
                                            <th>Usuario Consultante</th>
                                            <th>Documento / Objeto Accedido</th>
                                            <th>Motivo de Consulta</th>
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

<!-- Modal: Autorizar Acceso Confidencial -->
<div class="modal fade" id="modalAcceso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formAcceso" novalidate>
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-key me-1"></i>Conceder Acceso Confidencial</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-semibold">Tipo de Objeto <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="acc_tipo_objeto" required>
                                <option value="">-- Seleccionar Tipo --</option>
                                <option value="ml__normas">Norma Jurídica o Estándar</option>
                                <option value="ml__procedimientos">Procedimiento de Soporte</option>
                            </select>
                            <input type="hidden" id="acc_objeto_tabla" name="objeto_tabla">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label form-label-sm fw-semibold">Documento Reservado <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="acc_objeto_id" name="objeto_id" required>
                                <option value="">-- Primero elija el tipo de objeto --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Perfil Autorizado <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="acc_empresa_perfil_id" name="empresa_perfil_id" required>
                            <option value="">-- Seleccionar Perfil --</option>
                        </select>
                    </div>

                    <!-- Ámbito estricto (ck_acco_ambito) -->
                    <div class="p-2 border rounded mb-2 bg-light">
                        <label class="form-label form-label-sm fw-semibold text-primary">Nivel y Destino de Alcance <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" id="acc_ambito_tipo_id" name="ambito_tipo_id" required>
                                    <option value="">-- Nivel de Alcance --</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <div class="bloque-destino-acc" id="destAccGrupo" style="display: none;">
                                    <select class="form-select form-select-sm" id="acc_grupo_id" name="grupo_id">
                                        <option value="">-- Seleccionar Grupo --</option>
                                    </select>
                                </div>
                                <div class="bloque-destino-acc" id="destAccRegion" style="display: none;">
                                    <select class="form-select form-select-sm" id="acc_region_grupo_id" name="region_grupo_id">
                                        <option value="">-- Seleccionar Región --</option>
                                    </select>
                                </div>
                                <div class="bloque-destino-acc" id="destAccEmpresa" style="display: none;">
                                    <select class="form-select form-select-sm" id="acc_empresa_id" name="empresa_id">
                                        <option value="">-- Seleccionar Empresa --</option>
                                    </select>
                                </div>
                                <div class="bloque-destino-acc" id="destAccTerritorio" style="display: none;">
                                    <select class="form-select form-select-sm" id="acc_territorio_id" name="territorio_id">
                                        <option value="">-- Seleccionar Territorio --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="acc_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-semibold">Vigente Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="acc_vigente_hasta" name="vigente_hasta">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-key me-1"></i>Habilitar Acceso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="accesos_confidenciales.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
