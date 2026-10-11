<?php
/**
 * Vista: Recibos Oficiales y Cobranzas con Retenciones
 * Módulo 4: Estudio Contable (minigestion_recibos.php)
 * Página ID: 415
 */

$pageTitle = "Recibos y Cobranzas";
$currentPage = 'minigestion_recibos';
$modudo_idx = 4;
$pagina_idx = 415;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = intval($_SESSION['empresa_id'] ?? 2);

require_once __DIR__ . '/../../db.php';
$empresas_res = mysqli_query($conn, "SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Recibos y Cobranzas (Con Retenciones)
                    </h3>
                    <small class="text-muted">Emisión de recibos deduciendo certificados de retenciones sufridas con imputación contable automática</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard_estudio.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item"><a href="minigestion.php">Mini-Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Recibos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra Superior -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm mb-1 fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa
                            </label>
                            <select id="selEmpresaRecibo" class="form-select form-select-sm">
                                <?php while ($e = mysqli_fetch_assoc($empresas_res)): ?>
                                    <option value="<?= $e['empresa_id'] ?>" <?= ($e['empresa_id'] == $empresa_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['empresa']) ?> (CUIT: <?= $e['documento_numero'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-7 text-md-end mt-auto pb-1">
                            <button class="btn btn-primary btn-sm shadow-sm" id="btnNuevoRecibo">
                                <i class="fas fa-plus me-1"></i>+ Emitir Recibo Oficial
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Recibos -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-receipt me-1 text-primary"></i>Recibos Emitidos
                    </h6>
                    <div class="btn-group" role="group" aria-label="Exportar Recibos">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecibosRecargar" title="Recargar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecibosCopiar" title="Copiar">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnRecibosExcel" title="Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnRecibosPdf" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnRecibosCsv" title="CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecibosImprimir" title="Imprimir">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaRecibos" class="table table-bordered table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Número</th>
                                    <th>Cliente</th>
                                    <th>CUIT</th>
                                    <th class="text-end">Importe Total</th>
                                    <th class="text-center">Estado</th>
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

<!-- Modal Emitir Recibo -->
<div class="modal fade" id="modalRecibo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fw-bold" id="modalReciboLabel">
                    <i class="fas fa-receipt me-1"></i>Emitir Recibo Oficial de Cobranza
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRecibo">
                <input type="hidden" id="rec_empresa_id" name="empresa_id" value="<?= $empresa_id ?>">

                <div class="modal-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1 fw-bold">Cliente <span class="text-danger">*</span></label>
                            <select id="rec_entidad_id" name="entidad_id" class="form-select form-select-sm" required>
                                <option value="">-- Seleccionar Cliente --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" id="rec_f_emision" name="f_emision" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-bold">Punto de Venta</label>
                            <input type="number" id="rec_pv" name="comprobante_pv" class="form-control form-control-sm" value="1" min="1" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1 fw-bold">Medio de Cobro de Fondos</label>
                            <select id="rec_medio_pago" name="medio_pago" class="form-select form-select-sm">
                                <option value="EFECTIVO">Caja General (Efectivo)</option>
                                <option value="BANCO">Banco Cuenta Corriente (Transferencia/Depósito)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1 fw-bold text-primary">Importe Total del Recibo ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="rec_total" name="importe_total" class="form-control form-control-sm fw-bold text-end fs-6 text-primary" required placeholder="0.00">
                        </div>
                    </div>

                    <!-- Sección de Certificados de Retenciones Sufridas -->
                    <div class="card border border-warning bg-light p-2 mb-2">
                        <h6 class="fw-bold mb-2 text-dark small">
                            <i class="fas fa-file-invoice me-1 text-warning"></i>Certificados de Retenciones Sufridas (Deducciones)
                        </h6>
                        <div class="row g-2 align-items-center mb-1">
                            <div class="col-md-3">
                                <span class="small fw-semibold">Retención Ganancias:</span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="ret_ganancias_nro" class="form-control form-control-sm" placeholder="N° Certificado">
                            </div>
                            <div class="col-md-5">
                                <input type="number" step="0.01" name="ret_ganancias_importe" class="form-control form-control-sm text-end ret-importe" placeholder="Importe $ (0.00)">
                            </div>
                        </div>

                        <div class="row g-2 align-items-center mb-1">
                            <div class="col-md-3">
                                <span class="small fw-semibold">Retención IVA:</span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="ret_iva_nro" class="form-control form-control-sm" placeholder="N° Certificado">
                            </div>
                            <div class="col-md-5">
                                <input type="number" step="0.01" name="ret_iva_importe" class="form-control form-control-sm text-end ret-importe" placeholder="Importe $ (0.00)">
                            </div>
                        </div>

                        <div class="row g-2 align-items-center mb-1">
                            <div class="col-md-3">
                                <span class="small fw-semibold">Retención IIBB:</span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="ret_iibb_nro" class="form-control form-control-sm" placeholder="N° Certificado">
                            </div>
                            <div class="col-md-5">
                                <input type="number" step="0.01" name="ret_iibb_importe" class="form-control form-control-sm text-end ret-importe" placeholder="Importe $ (0.00)">
                            </div>
                        </div>

                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <span class="small fw-semibold">Retención SUSS:</span>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="ret_suss_nro" class="form-control form-control-sm" placeholder="N° Certificado">
                            </div>
                            <div class="col-md-5">
                                <input type="number" step="0.01" name="ret_suss_importe" class="form-control form-control-sm text-end ret-importe" placeholder="Importe $ (0.00)">
                            </div>
                        </div>
                    </div>

                    <div class="p-2 border rounded bg-white mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Total Retenciones Deducidas:</span>
                            <strong class="text-danger" id="lblTotalRet">$ 0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Neto a Cobrar en Fondos Disponibles:</span>
                            <strong class="text-success fs-6" id="lblNetoCobro">$ 0,00</strong>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label form-label-sm mb-1">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control form-control-sm" placeholder="Detalle adicional opcional...">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm" id="btnEmitirReciboSubmit">
                        <i class="fas fa-check-circle me-1"></i>Emitir Recibo y Asentar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="minigestion_recibos.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
