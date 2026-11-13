<?php
/**
 * Vista: Tablero de Control de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_tablero.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Tablero de Vencimientos";
$currentPage = 'vencimientos_tablero';
$modudo_idx = 4;
$pagina_idx = 403;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>Tablero de Control de Vencimientos
                    </h3>
                    <small class="text-muted">Monitoreo en tiempo real de obligaciones fiscales y previsionales con semáforo de urgencia</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tablero de Vencimientos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Widgets de KPIs / Semáforos -->
            <div class="row g-2 mb-3">
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card card-outline card-secondary shadow-sm text-center py-2 h-100">
                        <div class="fs-4 fw-bold text-secondary" id="kpi_total">0</div>
                        <div class="fs-8 text-muted text-uppercase fw-semibold">Total Instancias</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card card-outline card-danger shadow-sm text-center py-2 h-100 bg-danger bg-opacity-10">
                        <div class="fs-4 fw-bold text-danger" id="kpi_vencidos">0</div>
                        <div class="fs-8 text-danger text-uppercase fw-semibold"><i class="fas fa-exclamation-triangle me-1"></i>Vencidos / Críticos</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card card-outline card-warning shadow-sm text-center py-2 h-100">
                        <div class="fs-4 fw-bold text-warning" id="kpi_en_liq">0</div>
                        <div class="fs-8 text-muted text-uppercase fw-semibold">En Liquidación</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card card-outline card-info shadow-sm text-center py-2 h-100">
                        <div class="fs-4 fw-bold text-info" id="kpi_listos">0</div>
                        <div class="fs-8 text-muted text-uppercase fw-semibold">Listos p/ Presentar</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card card-outline card-primary shadow-sm text-center py-2 h-100">
                        <div class="fs-4 fw-bold text-primary" id="kpi_presentados">0</div>
                        <div class="fs-8 text-muted text-uppercase fw-semibold">Presentados</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card card-outline card-success shadow-sm text-center py-2 h-100">
                        <div class="fs-4 fw-bold text-success" id="kpi_pagados">0</div>
                        <div class="fs-8 text-muted text-uppercase fw-semibold"><i class="fas fa-check-circle me-1"></i>Pagados</div>
                    </div>
                </div>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Período Fiscal</label>
                            <input type="number" class="form-control form-control-sm" id="filtroPeriodo" placeholder="YYYYMM" value="<?= date('Ym') ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Cliente</label>
                            <select id="filtroCliente" class="form-select form-select-sm">
                                <option value="">-- Todos los clientes --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Obligación Tributaria</label>
                            <select id="filtroObligacion" class="form-select form-select-sm">
                                <option value="">-- Todas las obligaciones --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">Todos los estados</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-12 text-end mt-auto pb-1">
                            <a href="vencimientos_generador.php" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-cogs me-1"></i>Generador Masivo
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grilla Principal del Tablero -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-calendar-check me-2"></i>Tablero de Control</h6>
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
                        <table id="tablaVencimientos" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Semáforo</th>
                                    <th>Fecha Límite</th>
                                    <th>Cliente</th>
                                    <th>Obligación / Organismo</th>
                                    <th>Jurisdicción</th>
                                    <th class="text-end">A Pagar ($)</th>
                                    <th>VEP / Comprobante</th>
                                    <th>Estado</th>
                                    <th style="width: 130px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Actualización de Liquidación / Pago -->
<div class="modal fade" id="modalLiquidacion" tabindex="-1" aria-labelledby="modalLiquidacionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formLiquidacion">
                <input type="hidden" id="ml_vencimiento_id" name="vencimiento_id">
                <input type="hidden" id="ml_nuevo_estado_id" name="nuevo_estado_id">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalLiquidacionLabel">
                        <i class="fas fa-tasks me-2"></i>Actualizar Liquidación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-2 mb-3 bg-light rounded border">
                        <div class="fw-bold" id="ml_info_cliente">-</div>
                        <div class="text-muted fs-8" id="ml_info_obligacion">-</div>
                        <div class="text-muted fs-8">Vencimiento: <strong id="ml_info_flimite">-</strong></div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Imp. Determinado</label>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end" id="ml_importe_determinado" name="importe_determinado" value="0.00">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Saldo a Favor</label>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end" id="ml_importe_saldo_favor" name="importe_saldo_favor" value="0.00">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Importe a Pagar</label>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold text-primary" id="ml_importe_a_pagar" name="importe_a_pagar" value="0.00">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Nro. VEP / Comprobante</label>
                            <input type="text" class="form-control form-control-sm" id="ml_vep_nro" name="vep_nro" placeholder="Ej: 123456789">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>Confirmar Estado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<?php require_once __DIR__ . '/estudio_cliente_activo.php'; estudio_cliente_activo_script((int) $current_empresa_id); ?>
<script src="vencimientos_tablero.js?v=<?= (int) @filemtime(__DIR__ . '/vencimientos_tablero.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
