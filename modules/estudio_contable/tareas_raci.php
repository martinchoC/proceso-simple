<?php
/**
 * Vista: Matriz RACI y Time Tracking de Tareas
 * Módulo 4: Estudio Contable (tareas_raci.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Matriz RACI y Time Tracking";
$currentPage = 'tareas_raci';
$modudo_idx = 4;
$pagina_idx = 408;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-tasks me-2 text-primary"></i>Matriz RACI y Time Tracking
                    </h3>
                    <small class="text-muted">Gestión de tareas operativas por roles (R/A/C/I) y registro de horas dedicadas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Matriz RACI</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Responsable (R)</label>
                            <select id="filtroUsuarioR" class="form-select form-select-sm">
                                <option value="">-- Todos los Responsables --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Supervisor (A)</label>
                            <select id="filtroUsuarioA" class="form-select form-select-sm">
                                <option value="">-- Todos los Accountable --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Cliente</label>
                            <select id="filtroCliente" class="form-select form-select-sm">
                                <option value="">-- Todos los Clientes --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los Estados --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12 text-end">
                            <button class="btn btn-outline-secondary btn-sm me-1" id="btnFiltrar">
                                <i class="fas fa-filter me-1"></i>Filtrar
                            </button>
                            <button class="btn btn-outline-dark btn-sm me-2" id="btnLimpiar">
                                <i class="fas fa-undo me-1"></i>Limpiar
                            </button>
                            <button class="btn btn-success btn-sm shadow-sm" id="btnNuevaTarea">
                                <i class="fas fa-plus-circle me-1"></i>Nueva Tarea RACI
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Tareas RACI -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-tasks me-2"></i>Bandeja de Tareas RACI</h6>
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
                        <table id="tablaTareas" class="table table-sm table-hover table-striped w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Tarea / Título</th>
                                    <th>Cliente</th>
                                    <th>Límite</th>
                                    <th>Responsable (R)</th>
                                    <th>Accountable (A)</th>
                                    <th>Horas (Est / Real)</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center" style="width: 140px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Nueva / Editar Tarea RACI -->
<div class="modal fade" id="modalTarea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="modalTareaTitle">Nueva Tarea RACI</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formTarea">
                <input type="hidden" id="tarea_id" name="tarea_id" value="0">
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label class="form-label form-label-sm fw-bold">Cliente <span class="text-danger">*</span></label>
                            <select id="modal_cliente_id" name="cliente_id" class="form-select form-select-sm" required>
                                <option value="">Seleccione Cliente...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Estado</label>
                            <select id="modal_estado_id" name="tabla_estado_registro_id" class="form-select form-select-sm">
                                <!-- Opciones cargadas por JS -->
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold">Título de la Tarea <span class="text-danger">*</span></label>
                        <input type="text" id="modal_titulo" name="titulo" class="form-control form-control-sm" required placeholder="Ej: Liquidación F.931 Cargas Sociales">
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm text-muted">Descripción Operativa</label>
                        <textarea id="modal_descripcion" name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Detalle de pasos y requisitos..."></textarea>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold">Fecha Inicio</label>
                            <input type="date" id="modal_f_inicio" name="f_inicio" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold text-danger">Fecha Límite <span class="text-danger">*</span></label>
                            <input type="date" id="modal_f_limite" name="f_limite" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <div class="card card-body bg-light py-2 px-3 border mb-2">
                        <div class="fw-bold text-primary mb-1"><i class="fas fa-users-cog me-1"></i>Matriz de Responsabilidad RACI</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label form-label-sm fw-bold text-success">Responsible (R) - Quien ejecuta <span class="text-danger">*</span></label>
                                <select id="modal_usuario_r_id" name="usuario_r_id" class="form-select form-select-sm" required>
                                    <option value="">Seleccione operador...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm fw-bold text-warning-emphasis">Accountable (A) - Supervisor / Firma <span class="text-danger">*</span></label>
                                <select id="modal_usuario_a_id" name="usuario_a_id" class="form-select form-select-sm" required>
                                    <option value="">Seleccione supervisor...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm text-muted">Consulted (C) - Contactos consultados</label>
                                <input type="text" id="modal_contactos_c" name="contactos_c" class="form-control form-control-sm" placeholder="Ej: Dpto RRHH, Contador Externo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm text-muted">Informed (I) - Notificados</label>
                                <input type="text" id="modal_contactos_i" name="contactos_i" class="form-control form-control-sm" placeholder="Ej: Gerencia, Tesorería">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold">Horas Estimadas</label>
                            <input type="number" step="0.25" min="0.25" id="modal_horas_estimadas" name="horas_estimadas" class="form-control form-control-sm" value="2.0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Guardar Tarea</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Registrar Tiempo (Time Tracking) -->
<div class="modal fade" id="modalTiempo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title"><i class="fas fa-stopwatch me-1"></i>Registrar Tiempo Dedicado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formTiempo">
                <input type="hidden" id="tiempo_tarea_id" name="tarea_id" value="0">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold">Tarea</label>
                        <input type="text" id="tiempo_tarea_titulo" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold">Minutos Invertidos <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="tiempo_minutos" name="minutos" class="form-control form-control-sm" min="5" step="5" required placeholder="Ej: 45">
                            <span class="input-group-text">minutos</span>
                        </div>
                        <small class="text-muted">Ejemplo: 30 = 0.5 hs, 60 = 1 hs, 90 = 1.5 hs</small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold">Detalle del trabajo realizado <span class="text-danger">*</span></label>
                        <textarea id="tiempo_descripcion" name="descripcion" class="form-control form-control-sm" rows="3" required placeholder="Se revisaron comprobantes del período y se cerró la precarga en AFIP..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-circle me-1"></i>Imputar Minutos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Bitácora de Tiempos -->
<div class="modal fade" id="modalHistorialTiempos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title"><i class="fas fa-history me-1"></i>Historial de Tiempos Dedicados</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="historialTituloTarea" class="fw-bold text-primary mb-3"></h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tablaHistorialTiempos">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Operador</th>
                                <th>Minutos</th>
                                <th>Equiv. Horas</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="tareas_raci.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
