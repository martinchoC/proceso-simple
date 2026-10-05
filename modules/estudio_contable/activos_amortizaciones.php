<?php
/**
 * Vista: Mini-Gestión y Amortización de Activos Fijos
 * Módulo 4: Estudio Contable (activos_amortizaciones.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Activos Fijos y Amortizaciones";
$currentPage = 'activos_amortizaciones';
$modudo_idx = 4;
$pagina_idx = 410;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-boxes me-2 text-primary"></i>Activos Fijos y Amortizaciones
                    </h3>
                    <small class="text-muted">Padrón de bienes de uso, amortización mensual en lote y asientos contables automáticos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Activos Fijos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Categoría de Activo</label>
                            <select id="filtroCategoria" class="form-select form-select-sm">
                                <option value="">-- Todas las categorías --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Búsqueda rápida</label>
                            <input type="text" id="filtroBuscar" class="form-control form-control-sm" placeholder="Código o descripción...">
                        </div>
                        <div class="col-md-6 text-end mt-auto pb-1">
                            <button class="btn btn-outline-secondary btn-sm me-1" id="btnFiltrar">
                                <i class="fas fa-search me-1"></i>Buscar
                            </button>
                            <button class="btn btn-outline-dark btn-sm me-2" id="btnLimpiar">
                                <i class="fas fa-undo me-1"></i>Limpiar
                            </button>
                            <button class="btn btn-primary btn-sm me-1 shadow-sm" id="btnNuevoActivo">
                                <i class="fas fa-plus-circle me-1"></i>Nuevo Activo
                            </button>
                            <button class="btn btn-success btn-sm shadow-sm" id="btnAmortizarMes">
                                <i class="fas fa-calculator me-1"></i>Amortizar Período (AUTO)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Activos Fijos -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-boxes me-2"></i>Padrón de Bienes de Uso y Amortizaciones</h6>
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
                        <table id="tablaActivos" class="table table-sm table-hover table-striped w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 70px;">Código</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Adquisición</th>
                                    <th class="text-end">Valor Origen</th>
                                    <th class="text-end">Cuota Mensual</th>
                                    <th class="text-center">Avance (Meses)</th>
                                    <th class="text-end">Residual Contable</th>
                                    <th class="text-center" style="width: 110px;">Acciones</th>
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

<!-- Modal Alta / Edición de Activo Fijo -->
<div class="modal fade" id="modalActivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="modalActivoTitle">Registrar Activo Fijo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formActivo">
                <input type="hidden" id="activo_id" name="activo_id" value="0">
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Código Identificador <span class="text-danger">*</span></label>
                            <input type="text" id="modal_codigo" name="codigo" class="form-control form-control-sm" required placeholder="Ej: ROD-001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label form-label-sm fw-bold">Descripción / Bien de Uso <span class="text-danger">*</span></label>
                            <input type="text" id="modal_descripcion" name="descripcion" class="form-control form-control-sm" required placeholder="Ej: Camioneta Toyota Hilux 4x4">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Categoría</label>
                            <select id="modal_categoria" name="categoria_activo" class="form-select form-select-sm">
                                <!-- Cargado por JS -->
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Fecha de Adquisición</label>
                            <input type="date" id="modal_f_adquisicion" name="f_adquisicion" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Método Amortización</label>
                            <select id="modal_metodo" name="metodo_amortizacion" class="form-select form-select-sm">
                                <option value="LINEAL_MENSUAL">Lineal Mensual</option>
                                <option value="LINEAL_ANUAL">Lineal Anual</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Valor de Origen ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" id="modal_valor_origen" name="valor_origen" class="form-control form-control-sm" required placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Valor Residual / Descarte ($)</label>
                            <input type="number" step="0.01" min="0.00" id="modal_valor_residual" name="valor_residual" class="form-control form-control-sm" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-bold">Vida Útil (en Meses) <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="600" id="modal_vida_util_meses" name="vida_util_meses" class="form-control form-control-sm" value="60" required>
                            <small class="text-muted">Ej: 60 meses = 5 años, 120 = 10 años</small>
                        </div>
                    </div>

                    <!-- Imputación Contable -->
                    <div class="card card-body bg-light border py-2 px-3 mb-2">
                        <div class="fw-bold text-primary mb-2"><i class="fas fa-balance-scale me-1"></i>Cuentas Contables Imputables</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Cuenta Activo</label>
                                <select id="modal_cuenta_activo_id" name="cuenta_activo_id" class="form-select form-select-sm">
                                    <option value="0">-- Sin imputar --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Amortización Acumulada</label>
                                <select id="modal_cuenta_amort_acum_id" name="cuenta_amort_acum_id" class="form-select form-select-sm">
                                    <option value="0">-- Sin imputar --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Gasto Amortización</label>
                                <select id="modal_cuenta_gasto_amort_id" name="cuenta_gasto_amort_id" class="form-select form-select-sm">
                                    <option value="0">-- Sin imputar --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Guardar Activo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Historial de Amortizaciones del Activo -->
<div class="modal fade" id="modalHistorialAmort" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title"><i class="fas fa-history me-1"></i>Cuotas de Amortización Generadas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="historialTituloActivo" class="fw-bold text-primary mb-3"></h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="tablaHistorialAmort">
                        <thead class="table-light">
                            <tr>
                                <th>Período</th>
                                <th>Cuota N°</th>
                                <th class="text-end">Importe ($)</th>
                                <th>Fecha Asiento</th>
                                <th>Asiento ID</th>
                                <th>Descripción</th>
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

<!-- Modal Amortización Mensual en Lote -->
<div class="modal fade" id="modalLoteAmortizar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title"><i class="fas fa-cogs me-1"></i>Amortización Mensual en Lote</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formLoteAmortizar">
                <div class="modal-body">
                    <p class="text-muted small">
                        Este proceso calculará la cuota de amortización de todos los activos fijos vigentes que aún no hayan sido amortizados en el período seleccionado y generará automáticamente el Asiento Contable correspondiente.
                    </p>
                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-bold">Período Contable (AAAAMM) <span class="text-danger">*</span></label>
                        <input type="number" id="lote_periodo" name="periodo" class="form-control form-control-sm" required value="<?php echo date('Ym'); ?>" min="202001" max="203512">
                        <small class="text-muted">Ejemplo: <?php echo date('Ym'); ?> para el mes actual.</small>
                    </div>
                    <div class="alert alert-warning py-2 mb-0 small">
                        <i class="fas fa-exclamation-triangle me-1"></i>Se creará un asiento de origen <strong>AUTO</strong> con imputación al Debe (Gasto Amortización) y al Haber (Amortización Acumulada).
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-play me-1"></i>Ejecutar Amortización</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="activos_amortizaciones.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
