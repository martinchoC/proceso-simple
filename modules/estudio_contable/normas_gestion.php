<?php
/**
 * Vista: Normas Legales y Motor de Reglas Dinámicas
 * Módulo 4: Estudio Contable (normas_gestion.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Normas y Resoluciones";
$currentPage = 'normas_gestion';
$modudo_idx = 4;
$pagina_idx = 407;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-gavel me-2 text-primary"></i>Normas y Resoluciones Fiscales
                    </h3>
                    <small class="text-muted">Registro normativo oficial y motor de evaluación de reglas dinámicas sobre clientes</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Normas y Reglas</li>
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
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Organismo Emisor</label>
                            <select id="filtroOrganismo" class="form-select form-select-sm">
                                <option value="">-- Todos los organismos --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">Todos los estados</option>
                                <option value="3">Borrador</option>
                                <option value="5">Vigente / Impactada</option>
                                <option value="6">Derogada</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6"></div>
                        <div class="col-md-3 col-sm-6 text-end mt-auto pb-1">
                            <button type="button" class="btn btn-sm btn-primary w-100" id="btnNuevaNorma">
                                <i class="fas fa-plus-circle me-1"></i>Registrar Nueva Norma
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grilla Principal de Normas -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-gavel me-2"></i>Catálogo de Normas y Resoluciones</h6>
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
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablaNormas" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Organismo</th>
                                    <th>Norma / Resolución</th>
                                    <th>Título / Materia</th>
                                    <th>Vigencia Desde</th>
                                    <th>Reglas</th>
                                    <th>Impacto en Clientes</th>
                                    <th>Estado</th>
                                    <th style="width: 150px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Carga y Edición de Norma con Reglas Dinámicas -->
<div class="modal fade" id="modalNorma" tabindex="-1" aria-labelledby="modalNormaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formNorma">
                <input type="hidden" id="mn_norma_id" name="norma_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalNormaLabel">
                        <i class="fas fa-balance-scale me-2"></i>Registrar Resolución / Norma Legal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Organismo Emisor <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="mn_organismo_emisor" name="organismo_emisor" required>
                                <option value="ARCA">ARCA (ex AFIP)</option>
                                <option value="ARBA">ARBA (Buenos Aires)</option>
                                <option value="AGIP">AGIP (CABA)</option>
                                <option value="COMARB">COMARB (Convenio Multilateral)</option>
                                <option value="MTSS">Ministerio de Trabajo</option>
                                <option value="UIF">UIF</option>
                                <option value="OTRO">Otro Organismo</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Tipo de Norma</label>
                            <select class="form-select form-select-sm" id="mn_tipo_norma" name="tipo_norma">
                                <option value="RESOLUCION_GENERAL">Resolución General</option>
                                <option value="DECRETO">Decreto</option>
                                <option value="LEY">Ley</option>
                                <option value="DISPOSICION">Disposición</option>
                                <option value="CIRCULAR">Circular</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Número / Identificador <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="mn_nro_norma" name="nro_norma" required placeholder="Ej: RG 5500/2026">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Fecha Boletín Oficial</label>
                            <input type="date" class="form-control form-control-sm" id="mn_f_boletin_oficial" name="f_boletin_oficial" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Vigencia Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="mn_vigencia_desde" name="vigencia_desde" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Vigencia Hasta (Opcional)</label>
                            <input type="date" class="form-control form-control-sm" id="mn_vigencia_hasta" name="vigencia_hasta">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Título / Asunto Principal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="mn_titulo" name="titulo" required placeholder="Ej: Nuevo régimen de información mensual de compras y ventas">
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Resumen Técnico / Criterio del Estudio</label>
                        <textarea class="form-control form-control-sm" id="mn_resumen_tecnico" name="resumen_tecnico" rows="2" placeholder="Resumen del alcance legal y obligaciones que impone..."></textarea>
                    </div>

                    <div class="row g-2 p-2 border rounded bg-light mb-3">
                        <div class="col-md-6">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="mn_genera_nueva_obligacion" name="genera_nueva_obligacion" value="1">
                                <label class="form-check-label form-label-sm fw-semibold" for="mn_genera_nueva_obligacion">
                                    Genera Nueva Obligación Tributaria Automática
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" id="mn_impuesto_obligacion_id" name="impuesto_obligacion_id">
                                <option value="">-- Seleccione obligación a vincular --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Constructor de Reglas Condicionales -->
                    <div class="border rounded p-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fs-7 text-primary fw-bold mb-0">
                                <i class="fas fa-sliders-h me-1"></i>Reglas Dinámicas de Aplicabilidad sobre Clientes
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-primary" id="btnAgregarFilaRegla">
                                <i class="fas fa-plus me-1"></i>Agregar Condición
                            </button>
                        </div>
                        <small class="text-muted d-block mb-2 fs-8">Los clientes que cumplan todas las reglas serán clasificados como ALCANZADOS.</small>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="tablaReglasConstructor">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parámetro</th>
                                        <th>Operador</th>
                                        <th>Valor Esperado</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="reglas_body">
                                    <!-- Dinámico -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Norma
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Bitácora de Impacto en Clientes -->
<div class="modal fade" id="modalImpacto" tabindex="-1" aria-labelledby="modalImpactoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title fs-6" id="modalImpactoLabel">
                    <i class="fas fa-bullseye me-2"></i>Clientes Alcanzados por la Norma
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>CUIT</th>
                                <th>Condición IVA</th>
                                <th>Resultado Evaluación</th>
                                <th>Fecha Evaluación</th>
                            </tr>
                        </thead>
                        <tbody id="impacto_tbody">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="normas_gestion.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
