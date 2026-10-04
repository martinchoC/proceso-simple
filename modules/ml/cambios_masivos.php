<?php
/**
 * Vista de Cambios Masivos e Impacto (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/cambios_masivos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Cambios Masivos e Impacto";
$currentPage = 'cambios_masivos';
$modudo_idx = 3;
$pagina_idx = 363; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-network-wired me-2 text-warning"></i>Cambios Masivos e Impacto Normativo
                    </h3>
                    <small class="text-muted">Simulación de impacto, análisis de entidades alcanzadas y aprobación previa antes del recálculo (C35)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Cargas y Cambios Masivos</li>
                        <li class="breadcrumb-item active" aria-current="page">Cambios Masivos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-warning mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado de la Solicitud</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                                <option value="1">Pendientes de Aprobación</option>
                                <option value="2">Aprobados (Listos para Aplicar)</option>
                                <option value="3">Aplicados</option>
                            </select>
                        </div>
                        <div class="col-md-9 col-sm-6 text-end pt-3">
                            <button id="btnNuevaSimulacion" class="btn btn-sm btn-warning me-1" title="Preparar simulación de impacto masivo">
                                <i class="fas fa-calculator me-1"></i>Simular Impacto de Cambio
                            </button>
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Cambios Masivos -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaCambiosMasivos" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Norma Afectada</th>
                                    <th>Descripción del Cambio</th>
                                    <th>Impacto Proyectado</th>
                                    <th>Solicitado Por</th>
                                    <th>Aprobación / Aplicación</th>
                                    <th>Estado</th>
                                    <th style="width: 100px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Cargado vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Nueva Simulación de Impacto -->
<div class="modal fade" id="modalNuevaSimulacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formNuevaSimulacion">
                <div class="modal-header bg-warning py-2">
                    <h5 class="modal-title fs-6 text-dark">
                        <i class="fas fa-calculator me-1"></i>Simulación de Impacto de Cambio Normativo (C35)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle me-1"></i>Esta herramienta cuantifica todas las <strong>entidades, ciclos y tareas</strong> que se verán alcanzados por la modificación legal antes de requerir autorización formal y ejecutar la propagación en cascada.
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1 fw-bold">Norma y Versión Afectada <span class="text-danger">*</span></label>
                            <select id="modalNormaVersionId" name="norma_version_id" class="form-select form-select-sm" required>
                                <option value="">-- Seleccionar Versión de Norma --</option>
                            </select>
                        </div>
                        <div class="col-12 mt-2">
                            <label class="form-label form-label-sm mb-1 fw-bold">Descripción / Justificativo del Cambio <span class="text-danger">*</span></label>
                            <textarea id="modalDescripcion" name="descripcion" class="form-control form-control-sm" rows="3" placeholder="Detalle el alcance de la modificación, nueva exigencia o artículo modificado..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning" id="btnProcesarSimulacion">
                        <i class="fas fa-play me-1"></i>Calcular Impacto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Desglose de Impacto por Entidad -->
<div class="modal fade" id="modalDetalleImpacto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h5 class="modal-title fs-6">
                    <i class="fas fa-sitemap me-1"></i>Desglose de Entidades y Ciclos Impactados
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-2">
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Entidad Sujeta a Control</th>
                                <th style="width: 140px;" class="text-center">Ciclos Afectados</th>
                                <th style="width: 140px;" class="text-center">Tareas Afectadas</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyDetalleImpacto">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
<script src="cambios_masivos.js?v=<?= time() ?>"></script>
