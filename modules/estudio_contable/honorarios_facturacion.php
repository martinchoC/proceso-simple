<?php
/**
 * Vista: Facturación Recurrente de Abonos
 * Módulo 4: Estudio Contable (honorarios_facturacion.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Facturación de Abonos";
$currentPage = 'honorarios_facturacion';
$modudo_idx = 4;
$pagina_idx = 406;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-receipt me-2 text-primary"></i>Facturación de Abonos Mensuales
                    </h3>
                    <small class="text-muted">Consolidación de honorarios, cálculo de adicionales eventuales y emisión masiva conectada al ERP</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Facturación de Abonos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Selección de Período y Botón de Emisión -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label for="hf_periodo_fiscal" class="form-label form-label-sm mb-1 text-muted fw-semibold">Período de Facturación (YYYYMM)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control fw-bold" id="hf_periodo_fiscal" value="<?= date('Ym') ?>" min="200001" max="209912">
                                <button type="button" class="btn btn-primary" id="btnConsolidar">
                                    <i class="fas fa-sync-alt me-1"></i>Consolidar
                                </button>
                            </div>
                        </div>
                        <div class="col-md-5 col-sm-6">
                            <div class="d-flex align-items-center mt-3 pt-1">
                                <span class="fs-7 me-3 text-muted">Total a emitir: <strong class="fs-6 text-primary" id="txt_total_lote">$ 0,00</strong></span>
                                <span class="badge bg-secondary fs-8" id="txt_conteo_sel">0 seleccionados</span>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12 text-end mt-auto pb-1">
                            <button type="button" class="btn btn-sm btn-success fw-bold w-100" id="btnEmitirLote">
                                <i class="fas fa-file-invoice me-1"></i>Emitir Facturas Electrónicas en Lote
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grilla de Abonos Consolidados -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablaFacturacion" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="check_todos">
                                    </th>
                                    <th>Cliente</th>
                                    <th>Concepto / Tipo Doc</th>
                                    <th class="text-end">Base ($)</th>
                                    <th class="text-end">Adic. Legajos</th>
                                    <th class="text-end">Novedades ($)</th>
                                    <th class="text-end">Neto Gravado ($)</th>
                                    <th class="text-end">IVA 21% ($)</th>
                                    <th class="text-end fw-bold">Total Factura ($)</th>
                                    <th>Estado Facturación</th>
                                </tr>
                            </thead>
                            <tbody id="body_facturacion">
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">Haga clic en 'Consolidar' para calcular los abonos del período.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="honorarios_facturacion.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
