<?php
/**
 * Vista de Eventos Societarios y Cambios Estructurales (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/empresas_eventos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Eventos Societarios";
$currentPage = 'empresas_eventos';
$modudo_idx = 3;
$pagina_idx = 303; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-signature me-2 text-primary"></i>Eventos Societarios
                    </h3>
                    <small class="text-muted">Fusiones, escisiones, cambios de razón social e hitos corporativos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Eventos Societarios</li>
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
                            <label class="form-label form-label-sm mb-1 text-muted">Empresa Involucrada</label>
                            <select id="filtroEmpresa" class="form-select form-select-sm">
                                <option value="">-- Todas las empresas --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Evento</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado del Trámite</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="0">Activos (Borrador, Pendiente, Confirmado)</option>
                                <option value="3">Solo Borrador</option>
                                <option value="4">Pendiente de Aprobación</option>
                                <option value="5">Confirmados</option>
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
                        <table id="tablaEventos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center">ID</th>
                                    <th style="width: 110px;" class="text-center">Fecha</th>
                                    <th style="width: 200px;">Tipo de Evento</th>
                                    <th>Empresa Principal / Origen</th>
                                    <th>Empresa Destino / Absorbente</th>
                                    <th style="width: 150px;" class="text-center">Estado</th>
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

<!-- Modal: Registrar / Editar Evento -->
<div class="modal fade" id="modalEvento" tabindex="-1" aria-labelledby="modalEventoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEvento" novalidate>
                <input type="hidden" id="empresa_evento_id" name="empresa_evento_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalEventoLabel">
                        <i class="fas fa-file-contract me-1"></i> <span id="tituloModalEvento">Registrar Evento Societario</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_tipo_id" class="form-label form-label-sm fw-semibold">Tipo de Evento <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_tipo_id" name="empresa_evento_tipo_id" required>
                            <option value="">-- Seleccionar Tipo --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione el tipo de evento.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_empresa_origen_id" class="form-label form-label-sm fw-semibold">Empresa Principal / Origen <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_empresa_origen_id" name="empresa_origen_id" required>
                            <option value="">-- Seleccionar Empresa --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione la empresa involucrada.</div>
                    </div>
                    <div class="mb-3" id="bloqueEmpresaDestino">
                        <label for="modal_empresa_destino_id" class="form-label form-label-sm fw-semibold">Empresa Destino / Absorbente</label>
                        <select class="form-select form-select-sm" id="modal_empresa_destino_id" name="empresa_destino_id">
                            <option value="">-- Ninguna / No aplica --</option>
                        </select>
                        <small class="text-muted fs-8">Aplica para fusiones, absorciones o escisiones</small>
                    </div>
                    <div class="mb-3">
                        <label for="modal_fecha" class="form-label form-label-sm fw-semibold">Fecha Efectiva del Evento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="modal_fecha" name="fecha" required>
                        <div class="invalid-feedback">Indique la fecha efectiva.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_observaciones" class="form-label form-label-sm fw-semibold">Observaciones / Dictamen / Acta</label>
                        <textarea class="form-control form-control-sm" id="modal_observaciones" name="observaciones" rows="3" placeholder="Detalle número de resolución, asamblea o dictamen..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarEvento">
                        <i class="fas fa-save me-1"></i>Guardar en Borrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Evento e Impactos -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="modalConfirmarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formConfirmar" novalidate>
                <input type="hidden" id="confirmar_evento_id" name="evento_id" value="0">
                <input type="hidden" id="confirmar_nuevo_estado" name="nuevo_estado" value="5">
                <div class="modal-header bg-success text-white py-2">
                    <h5 class="modal-title fs-6" id="modalConfirmarLabel">
                        <i class="fas fa-check-double me-1"></i>Confirmación y Dictamen Legal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Está por confirmar definitivamente este evento societario. Esta acción consolidará los cambios en la estructura del grupo.</p>
                    <div class="mb-3" id="bloqueNuevaRazonSocial" style="display: none;">
                        <label for="modal_nueva_razon_social" class="form-label form-label-sm fw-semibold">Nueva Razón Social / Denominación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_nueva_razon_social" name="nueva_razon_social" placeholder="Ej: Nueva Denominación S.A.">
                        <small class="text-muted fs-8">Se registrará en el historial conservando la vigencia anterior.</small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success" id="btnEjecutarConfirmacion">
                        <i class="fas fa-check me-1"></i>Confirmar Definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="empresas_eventos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
