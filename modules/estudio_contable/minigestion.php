<?php
/**
 * Vista: Mini-Gestión de Clientes
 * Módulo 4: Estudio Contable (minigestion.php)
 * Página ID: 412
 */

$pageTitle = "Mini-Gestión de Clientes";
$currentPage = 'minigestion';
$modudo_idx = 4;
$pagina_idx = 412;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = intval($_SESSION['empresa_id'] ?? 2);

// Obtener clientes para el selector inicial
require_once __DIR__ . '/../../db.php';
$res_c = mysqli_query($conn, "SELECT cliente_id, razon_social, cuit, empresa_vinculada_id 
                               FROM estudio__clientes 
                               WHERE empresa_id = {$empresa_id} AND tabla_estado_registro_id = 1 
                               ORDER BY razon_social ASC");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-calculator me-2 text-primary"></i>Mini-Gestión de Clientes
                    </h3>
                    <small class="text-muted">Carga operativa para clientes externos y visualización/auditoría para empresas ERP</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard_estudio.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Mini-Gestión</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Selección y Control -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm mb-1 fw-bold text-dark">
                                <i class="fas fa-building me-1 text-primary"></i>Cliente del Estudio
                            </label>
                            <select id="selCliente" class="form-select form-select-sm">
                                <option value="">-- Seleccionar Cliente --</option>
                                <?php while ($c = mysqli_fetch_assoc($res_c)): ?>
                                    <option value="<?= $c['cliente_id'] ?>" data-vinculada="<?= intval($c['empresa_vinculada_id']) ?>">
                                        <?= htmlspecialchars($c['razon_social']) ?> (CUIT: <?= $c['cuit'] ?>)
                                        <?= ($c['empresa_vinculada_id'] > 0) ? ' [ERP]' : ' [Mini-Gestión]' ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold text-dark">
                                <i class="fas fa-calendar-alt me-1 text-primary"></i>Período Fiscal
                            </label>
                            <input type="month" id="filtroPeriodo" class="form-control form-control-sm" value="<?= date('Y-m') ?>">
                        </div>
                        <div class="col-md-4 text-md-end mt-auto pb-1">
                            <button class="btn btn-outline-secondary btn-sm me-1" id="btnLimpiarFiltros" title="Ver todos los períodos">
                                <i class="fas fa-calendar-times me-1"></i>Todo el Año
                            </button>
                            <button class="btn btn-primary btn-sm shadow-sm" id="btnActualizarDatos">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banner Informativo de Modo (Dinámico) -->
            <div id="bannerModo" class="alert alert-secondary py-2 mb-3 shadow-sm d-flex align-items-center" style="display: none !important;">
                <div class="me-3 fs-3" id="bannerIcono">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold" id="bannerTitulo">Seleccione un cliente para operar</div>
                    <small id="bannerDescripcion" class="text-muted">El sistema detectará automáticamente si corresponde a carga manual o visualización ERP.</small>
                </div>
                <div id="bannerBadge">
                    <span class="badge bg-secondary">Sin selección</span>
                </div>
            </div>

            <!-- Tarjetas de Resumen Fiscal (KPIs) -->
            <div class="row g-3 mb-3" id="panelKpis">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-body-tertiary">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Ventas Netas</span>
                                    <h4 class="mb-0 fw-bold text-primary" id="kpiVentasNeto">$ 0,00</h4>
                                    <small class="text-muted" id="kpiCantVentas">0 comprobantes</small>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary rounded p-3">
                                    <i class="fas fa-arrow-up fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-body-tertiary">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">IVA Débito Fiscal</span>
                                    <h4 class="mb-0 fw-bold text-danger" id="kpiIvaDebito">$ 0,00</h4>
                                    <small class="text-muted">Generado en ventas</small>
                                </div>
                                <div class="bg-danger bg-opacity-10 text-danger rounded p-3">
                                    <i class="fas fa-file-invoice-dollar fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-body-tertiary">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Compras Netas</span>
                                    <h4 class="mb-0 fw-bold text-success" id="kpiComprasNeto">$ 0,00</h4>
                                    <small class="text-muted" id="kpiCantCompras">0 comprobantes</small>
                                </div>
                                <div class="bg-success bg-opacity-10 text-success rounded p-3">
                                    <i class="fas fa-arrow-down fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-body-tertiary">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-semibold text-uppercase">Posición Neta IVA</span>
                                    <h4 class="mb-0 fw-bold text-dark" id="kpiPosicionIva">$ 0,00</h4>
                                    <small id="kpiSaldoDetalle" class="badge bg-secondary">Sin cálculo</small>
                                </div>
                                <div class="bg-dark bg-opacity-10 text-dark rounded p-3">
                                    <i class="fas fa-balance-scale fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de Comprobantes -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header p-2 bg-white border-bottom">
                    <ul class="nav nav-pills" id="tabsMinigestion" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-bold" id="tab-ventas-btn" data-bs-toggle="pill" data-bs-target="#tab-ventas" type="button" role="tab">
                                <i class="fas fa-sign-out-alt me-1 text-primary"></i>Ventas e Ingresos
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" id="tab-compras-btn" data-bs-toggle="pill" data-bs-target="#tab-compras" type="button" role="tab">
                                <i class="fas fa-sign-in-alt me-1 text-success"></i>Compras y Gastos
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" id="tab-resumen-btn" data-bs-toggle="pill" data-bs-target="#tab-resumen" type="button" role="tab">
                                <i class="fas fa-chart-pie me-1 text-info"></i>Posición Fiscal / Subdiarios
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-3">
                    <div class="tab-content" id="tabContentMinigestion">
                        <!-- TAB 1: VENTAS -->
                        <div class="tab-pane fade show active" id="tab-ventas" role="tabpanel">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                                <div>
                                    <button class="btn btn-primary btn-sm shadow-sm" id="btnNuevaVenta" style="display: none;">
                                        <i class="fas fa-plus me-1"></i>+ Cargar Comprobante de Venta
                                    </button>
                                    <span id="badgeAvisoErpVentas" class="badge bg-success py-2 px-3 fs-7" style="display: none;">
                                        <i class="fas fa-eye me-1"></i>Visualizando comprobantes emitidos en el ERP de la empresa
                                    </span>
                                </div>
                                <div class="btn-group" role="group" aria-label="Exportar Ventas">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVentasRecargar" title="Recargar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVentasCopiar" title="Copiar">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" id="btnVentasExcel" title="Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnVentasPdf" title="PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnVentasCsv" title="CSV">
                                        <i class="fas fa-file-csv"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVentasImprimir" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaVentas" class="table table-bordered table-striped table-hover align-middle w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Comprobante</th>
                                            <th>Receptor / Cliente</th>
                                            <th>CUIT</th>
                                            <th class="text-end">Neto Gravado</th>
                                            <th class="text-end">IVA</th>
                                            <th class="text-end">No Grav./Ex.</th>
                                            <th class="text-end">Total</th>
                                            <th>Origen</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: COMPRAS -->
                        <div class="tab-pane fade" id="tab-compras" role="tabpanel">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                                <div>
                                    <button class="btn btn-success btn-sm shadow-sm" id="btnNuevaCompra" style="display: none;">
                                        <i class="fas fa-plus me-1"></i>+ Cargar Comprobante de Compra
                                    </button>
                                    <span id="badgeAvisoErpCompras" class="badge bg-success py-2 px-3 fs-7" style="display: none;">
                                        <i class="fas fa-eye me-1"></i>Visualizando facturas de proveedores registradas en el ERP
                                    </span>
                                </div>
                                <div class="btn-group" role="group" aria-label="Exportar Compras">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnComprasRecargar" title="Recargar">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnComprasCopiar" title="Copiar">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" id="btnComprasExcel" title="Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnComprasPdf" title="PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnComprasCsv" title="CSV">
                                        <i class="fas fa-file-csv"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnComprasImprimir" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaCompras" class="table table-bordered table-striped table-hover align-middle w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Comprobante</th>
                                            <th>Proveedor</th>
                                            <th>CUIT</th>
                                            <th class="text-end">Neto Gravado</th>
                                            <th class="text-end">IVA</th>
                                            <th class="text-end">Percepciones</th>
                                            <th class="text-end">Total</th>
                                            <th>Origen</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 3: RESUMEN FISCAL -->
                        <div class="tab-pane fade" id="tab-resumen" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card border shadow-none">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-receipt me-1 text-primary"></i>Liquidación Técnica de IVA</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <table class="table table-sm table-borderless mb-0">
                                                <tr>
                                                    <td class="text-muted">Total Ventas Facturadas (Neto):</td>
                                                    <td class="text-end fw-bold" id="resVentasNeto">$ 0,00</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">(+) Débito Fiscal IVA Generado:</td>
                                                    <td class="text-end fw-bold text-danger" id="resIvaDebito">$ 0,00</td>
                                                </tr>
                                                <tr class="border-top">
                                                    <td class="text-muted">Total Compras Facturadas (Neto):</td>
                                                    <td class="text-end fw-bold" id="resComprasNeto">$ 0,00</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">(-) Crédito Fiscal IVA Computable:</td>
                                                    <td class="text-end fw-bold text-success" id="resIvaCredito">$ 0,00</td>
                                                </tr>
                                                <tr class="border-top table-light">
                                                    <td class="fw-bold">(=) Saldo Técnico Resultante:</td>
                                                    <td class="text-end fw-bold fs-6" id="resSaldoTecnico">$ 0,00</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted">(-) Retenciones y Percepciones:</td>
                                                    <td class="text-end fw-bold text-info" id="resPercepciones">$ 0,00</td>
                                                </tr>
                                                <tr class="border-top table-active">
                                                    <td class="fw-bold fs-6">(=) Posición Fiscal a Ingresar / (Favor):</td>
                                                    <td class="text-end fw-bold fs-5" id="resPosicionFinal">$ 0,00</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border shadow-none">
                                        <div class="card-header bg-light py-2">
                                            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-file-export me-1 text-success"></i>Exportaciones y Archivos de Control</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <p class="text-muted small">Los datos consolidados pueden exportarse para la generación del Libro IVA Digital (ARCA) o informes gerenciales para el cliente.</p>
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-outline-success btn-sm text-start" id="btnExportarLibroVentas">
                                                    <i class="fas fa-file-excel me-2"></i>Exportar Libro IVA Ventas del Período
                                                </button>
                                                <button class="btn btn-outline-primary btn-sm text-start" id="btnExportarLibroCompras">
                                                    <i class="fas fa-file-excel me-2"></i>Exportar Libro IVA Compras del Período
                                                </button>
                                                <a href="activos_amortizaciones.php" class="btn btn-outline-secondary btn-sm text-start">
                                                    <i class="fas fa-boxes me-2"></i>Ir a Activos Fijos y Amortizaciones del Cliente
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Cargar / Editar Comprobante Manual (Modo Mini-Gestión) -->
<div class="modal fade" id="modalComprobante" tabindex="-1" aria-labelledby="modalComprobanteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fw-bold" id="modalComprobanteLabel">
                    <i class="fas fa-file-invoice me-1"></i>Cargar Comprobante
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formComprobante">
                <input type="hidden" id="comp_id" name="comprobante_id" value="0">
                <input type="hidden" id="comp_cliente_id" name="cliente_id" value="0">
                <input type="hidden" id="comp_tipo_operacion" name="tipo_operacion" value="VENTA">

                <div class="modal-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1 fw-bold">Tipo de Operación</label>
                            <input type="text" id="comp_tipo_op_display" class="form-control form-control-sm bg-light fw-bold" readonly value="VENTA">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1 fw-bold">Fecha de Emisión <span class="text-danger">*</span></label>
                            <input type="date" id="comp_f_emision" name="f_emision" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1 fw-bold">Tipo Comprobante <span class="text-danger">*</span></label>
                            <select id="comp_tipo_id" name="comprobante_tipo_id" class="form-select form-select-sm" required>
                                <option value="4" data-letra="A">Factura A</option>
                                <option value="12" data-letra="B">Factura B</option>
                                <option value="1" data-letra="C">Factura C</option>
                                <option value="7" data-letra="A">Nota de Crédito A</option>
                                <option value="13" data-letra="B">Nota de Crédito B</option>
                                <option value="8" data-letra="A">Nota de Débito A</option>
                            </select>
                            <input type="hidden" id="comp_letra" name="comprobante_letra" value="A">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold">Punto de Venta</label>
                            <input type="number" id="comp_pv" name="punto_venta" class="form-control form-control-sm" min="1" max="99999" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold">Número Comprobante <span class="text-danger">*</span></label>
                            <input type="number" id="comp_nro" name="comprobante_nro" class="form-control form-control-sm" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold" id="lblContraparteCuit">CUIT Contraparte</label>
                            <input type="text" id="comp_cuit" name="cuit_contraparte" class="form-control form-control-sm" placeholder="20-xxxxxxxx-x">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold" id="lblContraparteNombre">Razón Social Contraparte</label>
                            <input type="text" id="comp_razon" name="razon_social_contraparte" class="form-control form-control-sm" placeholder="Nombre / Razón Social" required>
                        </div>
                    </div>

                    <div class="p-2 border rounded bg-light mb-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1 fw-bold">Neto Gravado ($)</label>
                                <input type="number" step="0.01" id="comp_neto" name="neto_gravado" class="form-control form-control-sm text-end fw-bold" value="0.00" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1 fw-bold">Alícuota IVA</label>
                                <select id="comp_alicuota" name="alicuota_iva" class="form-select form-select-sm">
                                    <option value="21.00">21,00 %</option>
                                    <option value="10.50">10,50 %</option>
                                    <option value="27.00">27,00 %</option>
                                    <option value="0.00">0,00 % (Exento)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1 fw-bold">IVA Liquidado ($)</label>
                                <input type="number" step="0.01" id="comp_iva" name="importe_iva" class="form-control form-control-sm text-end fw-bold text-danger" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1 fw-bold">No Gravado / Exento ($)</label>
                                <input type="number" step="0.01" id="comp_nograv" name="no_gravado_exento" class="form-control form-control-sm text-end" value="0.00">
                            </div>
                        </div>

                        <div class="row g-2 align-items-center mt-1">
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1 fw-bold">Percep. IIBB ($)</label>
                                <input type="number" step="0.01" id="comp_percep_iibb" name="percepcion_iibb" class="form-control form-control-sm text-end" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1 fw-bold">Percep. IVA ($)</label>
                                <input type="number" step="0.01" id="comp_percep_iva" name="percepcion_iva" class="form-control form-control-sm text-end" value="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm mb-1 fw-bold text-primary">Importe Total ($)</label>
                                <input type="number" step="0.01" id="comp_total" name="importe_total" class="form-control form-control-sm text-end fw-bold fs-6 text-primary" value="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1">Observaciones</label>
                            <input type="text" id="comp_obs" name="observaciones" class="form-control form-control-sm" placeholder="Detalle adicional opcional...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm" id="btnGuardarComprobante">
                        <i class="fas fa-save me-1"></i>Guardar Comprobante
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="minigestion.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
