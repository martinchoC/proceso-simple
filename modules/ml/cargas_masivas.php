<?php
/**
 * Vista de Cargas Masivas (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/cargas_masivas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Cargas Masivas de Datos";
$currentPage = 'cargas_masivas';
$modudo_idx = 3;
$pagina_idx = 362; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-import me-2 text-primary"></i>Cargas Masivas de Datos
                    </h3>
                    <small class="text-muted">Importador estructurado de normas, obligaciones y entidades con validación y rollback atómico (C36)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Cargas y Cambios Masivos</li>
                        <li class="breadcrumb-item active" aria-current="page">Cargas Masivas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Carga</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado del Lote</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                                <option value="2">Validadas OK</option>
                                <option value="3">Con Errores</option>
                                <option value="4">Confirmadas</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-sm-12 text-md-end text-center pt-md-3 pt-1">
                            <div class="btn-group me-1">
                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-download me-1"></i>Descargar Plantilla
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item btn-descargar-plantilla" href="#" data-tipo="NORMAS"><i class="fas fa-book me-2 text-primary"></i>Plantilla Normas</a></li>
                                    <li><a class="dropdown-item btn-descargar-plantilla" href="#" data-tipo="OBLIGACIONES"><i class="fas fa-tasks me-2 text-warning"></i>Plantilla Obligaciones</a></li>
                                    <li><a class="dropdown-item btn-descargar-plantilla" href="#" data-tipo="ENTIDADES"><i class="fas fa-building me-2 text-info"></i>Plantilla Entidades</a></li>
                                </ul>
                            </div>
                            <button id="btnNuevaCarga" class="btn btn-sm btn-primary me-1" title="Subir y validar nueva planilla CSV">
                                <i class="fas fa-upload me-1"></i>Nueva Carga
                            </button>
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Cargas Masivas -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaCargas" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Tipo de Carga</th>
                                    <th>Archivo</th>
                                    <th>Filas (Total / OK / Error)</th>
                                    <th>Subida / Validación</th>
                                    <th>Confirmación</th>
                                    <th>Estado</th>
                                    <th style="width: 90px;" class="text-center">Acciones</th>
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

<!-- Modal Subir y Validar Carga -->
<div class="modal fade" id="modalNuevaCarga" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formNuevaCarga" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6">
                        <i class="fas fa-file-upload me-1"></i>Subir y Validar Planilla Masiva (C36)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle me-1"></i>El archivo debe ser formato <strong>CSV</strong> delimitado por punto y coma (<code>;</code>) con encabezados oficiales. El sistema validará cada fila antes de habilitar la confirmación.
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm mb-1 fw-bold">Tipo de Carga Masiva <span class="text-danger">*</span></label>
                        <select id="modalCargaTipoId" name="carga_tipo_id" class="form-select form-select-sm" required>
                            <option value="">-- Seleccionar Tipo --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm mb-1 fw-bold">Archivo CSV <span class="text-danger">*</span></label>
                        <input type="file" id="modalArchivo" name="archivo" class="form-control form-control-sm" accept=".csv,text/csv" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnProcesarCarga">
                        <i class="fas fa-cogs me-1"></i>Subir y Validar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Vista Previa de Filas y Errores -->
<div class="modal fade" id="modalVistaPrevia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h5 class="modal-title fs-6">
                    <i class="fas fa-table me-1"></i>Vista Previa y Diagnóstico de Filas de la Carga
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-2">
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped align-middle mb-0" id="tablaVistaPreviaFilas">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th style="width: 60px;">Fila</th>
                                <th>Contenido Analizado</th>
                                <th style="width: 280px;">Diagnóstico / Errores</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyVistaPrevia">
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
<script src="cargas_masivas.js?v=<?= time() ?>"></script>
