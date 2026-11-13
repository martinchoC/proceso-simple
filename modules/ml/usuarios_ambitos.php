<?php
/**
 * Vista de Ámbitos de Usuarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/usuarios_ambitos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Ámbitos de Usuarios";
$currentPage = 'usuarios_ambitos';
$modudo_idx = 3;
$pagina_idx = 309; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-user-shield me-2 text-primary"></i>Ámbitos y Alcances de Usuarios
                    </h3>
                    <small class="text-muted">Asignación de responsabilidad por país, empresa, región o grupo corporativo</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ámbitos de Usuarios</li>
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
                            <label class="form-label form-label-sm mb-1 text-muted">Usuario</label>
                            <select id="filtroUsuario" class="form-select form-select-sm">
                                <option value="">-- Todos los usuarios --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Ámbito</label>
                            <select id="filtroTipoAmbito" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Vigencia</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="1">Solo Activos</option>
                                <option value="0">Todos (Incluye Cerrados)</option>
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
                        <table id="tablaAmbitos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center">ID</th>
                                    <th>Usuario</th>
                                    <th>Perfil Asignado</th>
                                    <th style="width: 160px;">Tipo de Ámbito</th>
                                    <th>Alcance / Destino Habilitado</th>
                                    <th style="width: 120px;" class="text-center">Vigente Desde</th>
                                    <th style="width: 120px;" class="text-center">Vigente Hasta</th>
                                    <th style="width: 90px;" class="text-center">Estado</th>
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
</main>

<!-- Modal: Asignar Ámbito -->
<div class="modal fade" id="modalAmbito" tabindex="-1" aria-labelledby="modalAmbitoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formAmbito" novalidate>
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalAmbitoLabel">
                        <i class="fas fa-user-plus me-1"></i>Asignar Ámbito a Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_usuario_id" class="form-label form-label-sm fw-semibold">Usuario <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_usuario_id" name="usuario_id" required>
                            <option value="">-- Seleccionar Usuario --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione un usuario.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_empresa_perfil_id" class="form-label form-label-sm fw-semibold">Perfil en Matriz Legal <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_empresa_perfil_id" name="empresa_perfil_id" required>
                            <option value="">-- Seleccionar Perfil --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione el perfil a aplicar en este ámbito.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_ambito_tipo_id" class="form-label form-label-sm fw-semibold">Nivel de Alcance <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_ambito_tipo_id" name="ambito_tipo_id" required>
                            <option value="">-- Seleccionar Tipo de Ámbito --</option>
                        </select>
                        <div class="invalid-feedback">Indique el nivel del ámbito.</div>
                    </div>

                    <!-- Destinos dinámicos según el tipo de ámbito -->
                    <div class="mb-3 bloque-destino" id="bloqueGrupo" style="display: none;">
                        <label for="modal_grupo_id" class="form-label form-label-sm fw-semibold">Grupo Corporativo <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_grupo_id" name="grupo_id">
                            <option value="">-- Seleccionar Grupo --</option>
                        </select>
                    </div>

                    <div class="mb-3 bloque-destino" id="bloqueRegion" style="display: none;">
                        <label for="modal_region_grupo_id" class="form-label form-label-sm fw-semibold">Región Corporativa <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_region_grupo_id" name="region_grupo_id">
                            <option value="">-- Seleccionar Región --</option>
                        </select>
                    </div>

                    <div class="mb-3 bloque-destino" id="bloqueEmpresa" style="display: none;">
                        <label for="modal_empresa_id" class="form-label form-label-sm fw-semibold">Empresa <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_empresa_id" name="empresa_id">
                            <option value="">-- Seleccionar Empresa --</option>
                        </select>
                    </div>

                    <div class="mb-3 bloque-destino" id="bloqueTerritorio" style="display: none;">
                        <label for="modal_territorio_id" class="form-label form-label-sm fw-semibold">Territorio / País <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_territorio_id" name="territorio_id">
                            <option value="">-- Seleccionar Territorio --</option>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label for="modal_vigente_desde" class="form-label form-label-sm fw-semibold">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="modal_vigente_desde" name="vigente_desde" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="modal_vigente_hasta" class="form-label form-label-sm fw-semibold">Vigente Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="modal_vigente_hasta" name="vigente_hasta">
                            <small class="text-muted fs-8">Opcional para asignación continua</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarAmbito">
                        <i class="fas fa-save me-1"></i>Asignar Ámbito
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="usuarios_ambitos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
