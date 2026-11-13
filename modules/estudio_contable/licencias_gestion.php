<?php
/**
 * Vista: Gestión de Licencias y Delegación Automática de Tareas
 * Módulo 4: Estudio Contable (licencias_gestion.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Licencias y Delegación de Tareas";
$currentPage = 'licencias_gestion';
$modudo_idx = 4;
$pagina_idx = 409;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>Licencias y Delegación de Tareas
                    </h3>
                    <small class="text-muted">Wizard de ausencias temporales, traspaso de responsabilidades RACI y reversión automática</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Licencias</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Acciones y Filtros -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Titular de Licencia</label>
                            <select id="filtroUsuario" class="form-select form-select-sm">
                                <option value="">-- Todos los Usuarios --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Licencia</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="">-- Todos los Tipos --</option>
                                <option value="VACACIONES">Vacaciones</option>
                                <option value="MEDICA">Médica</option>
                                <option value="ESTUDIO">Estudio / Examen</option>
                                <option value="OTRA">Otra</option>
                            </select>
                        </div>
                        <div class="col-md-5 text-end mt-auto pb-1">
                            <button class="btn btn-outline-secondary btn-sm me-1" id="btnFiltrar">
                                <i class="fas fa-filter me-1"></i>Filtrar
                            </button>
                            <button class="btn btn-outline-dark btn-sm me-2" id="btnLimpiar">
                                <i class="fas fa-undo me-1"></i>Limpiar
                            </button>
                            <button class="btn btn-success btn-sm shadow-sm" id="btnNuevaLicencia" data-funcion="agregar">
                                <i class="fas fa-plus-circle me-1"></i>Programar Licencia / Wizard
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Licencias -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-calendar-alt me-2"></i>Padrón de Licencias y Suplencias</h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarCopy" title="Copiar al portapapeles">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcel" title="Exportar a Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDF" title="Exportar a PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportarCSV" title="Exportar a CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarPrint" title="Imprimir tabla">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaLicencias" class="table table-sm table-hover table-striped w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Titular</th>
                                    <th>Tipo</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Suplente R</th>
                                    <th>Suplente A</th>
                                    <th class="text-center">Delegaciones</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center" style="width: 120px;">Acciones</th>
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

<!-- Modal Wizard Nueva Licencia -->
<div class="modal fade" id="modalLicencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title"><i class="fas fa-user-clock me-2"></i>Wizard de Licencia y Traspaso de Tareas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formLicencia">
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold">Colaborador Titular <span class="text-danger">*</span></label>
                            <select id="modal_usuario_id" name="usuario_id" class="form-select form-select-sm" required>
                                <option value="">Seleccione Titular...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold">Tipo de Licencia <span class="text-danger">*</span></label>
                            <select id="modal_tipo_licencia" name="tipo_licencia" class="form-select form-select-sm" required>
                                <option value="VACACIONES">Vacaciones</option>
                                <option value="MEDICA">Licencia Médica</option>
                                <option value="ESTUDIO">Días por Estudio / Examen</option>
                                <option value="OTRA">Otra</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold">Fecha Desde <span class="text-danger">*</span></label>
                            <input type="date" id="modal_f_desde" name="f_desde" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold">Fecha Hasta <span class="text-danger">*</span></label>
                            <input type="date" id="modal_f_hasta" name="f_hasta" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <!-- Configuración de Suplentes -->
                    <div class="card card-body bg-light border py-2 px-3 mb-3">
                        <div class="fw-bold text-primary mb-2"><i class="fas fa-exchange-alt me-1"></i>Asignación de Suplentes Automáticos</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label form-label-sm fw-bold text-success">Suplente de Ejecución (Rol R)</label>
                                <select id="modal_suplente_r_id" name="usuario_suplente_r_id" class="form-select form-select-sm">
                                    <option value="">-- Sin suplente R --</option>
                                </select>
                                <small class="text-muted">Reemplazará al titular en tareas que ejecuta.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm fw-bold text-warning-emphasis">Suplente de Firma / Aprobación (Rol A)</label>
                                <select id="modal_suplente_a_id" name="usuario_suplente_a_id" class="form-select form-select-sm">
                                    <option value="">-- Sin suplente A --</option>
                                </select>
                                <small class="text-muted">Reemplazará al titular en tareas que supervisa.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-sm">Observaciones / Motivo</label>
                        <input type="text" id="modal_observaciones" name="observaciones" class="form-control form-control-sm" placeholder="Ej: Vacaciones pactadas según convenio...">
                    </div>

                    <!-- Vista Previa de Impacto en Tareas -->
                    <div class="border rounded p-2 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark"><i class="fas fa-list-check me-1"></i>Impacto en Tareas Activas</span>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0" id="btnPreviewImpacto">
                                <i class="fas fa-sync-alt me-1"></i>Calcular Tareas Afectadas
                            </button>
                        </div>
                        <div id="contenedorPreviewImpacto" style="max-height: 180px; overflow-y: auto;">
                            <p class="text-muted text-center my-2 small">Haga clic en 'Calcular Tareas Afectadas' para ver las tareas que se transferirán.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Confirmar y Delegar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="licencias_gestion.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
