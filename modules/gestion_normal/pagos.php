<?php
/**
 * pagos.php
 *
 * Módulo de Pagos a Proveedores (Órdenes de Pago OP)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Órdenes de Pago a Proveedores (OP)";
$currentPage = 'pagos';
$modudo_idx = 5; // Typo intencional y consistente con todo el ERP
$pagina_idx = 606;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-money-check-alt text-primary me-2"></i>Órdenes de Pago a Proveedores
                    </h3>
                    <small class="text-muted">Emisión de OP, Cancelación de Facturas de Compra, Emisión y Endoso de Cheques</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="#">Tesorería</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Órdenes de Pago</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="fas fa-file-invoice-dollar me-2 text-secondary"></i>Órdenes de Pago y Borradores
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar listado">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoPago">
                                <i class="fas fa-plus me-1"></i> Nueva Orden de Pago
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body bg-light border-bottom py-2">
                    <form id="formFiltros" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Fecha Desde</label>
                            <input type="date" class="form-control form-control-sm" id="filtroDesde" name="desde">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Fecha Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="filtroHasta" name="hasta">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Proveedor</label>
                            <select class="form-select form-control-sm select2" id="filtroProveedor" name="entidad_id">
                                <option value="">-- Todos los Proveedores --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Estado</label>
                            <select class="form-select form-control-sm" id="filtroEstado" name="estado_id">
                                <option value="">-- Todos --</option>
                                <option value="3">Borrador</option>
                                <option value="5">Confirmado</option>
                                <option value="38">Anulado</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-primary flex-fill" id="btnFiltrar" title="Filtrar">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros" title="Limpiar Filtros">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaPagos" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 80px;">Emisión</th>
                                    <th style="width: 140px;">Orden N°</th>
                                    <th>Proveedor</th>
                                    <th style="width: 120px;">Sucursal</th>
                                    <th class="text-end" style="width: 130px;">Total Pagado</th>
                                    <th style="width: 160px;">Aplicación</th>
                                    <th class="text-center" style="width: 110px;">Estado</th>
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

<!-- MODAL DE EMISIÓN / EDICIÓN DE ORDEN DE PAGO -->
<div class="modal fade" id="modalPago" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalPagoLabel">
                    <i class="fas fa-money-check-alt me-2"></i>Nueva Orden de Pago (OP)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <form id="formPago">
                    <input type="hidden" id="pago_id" name="pago_id" value="0">

                    <!-- SECCIÓN 1: CABECERA -->
                    <div class="card border mb-3 shadow-none">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0 fw-bold text-primary small text-uppercase">
                                <i class="fas fa-truck me-1"></i> 1. Datos del Proveedor y Emisión
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="selectProveedor" name="entidad_id" required>
                                        <option value="">-- Seleccionar Proveedor --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Sucursal <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="selectSucursal" name="sucursal_id" required>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="inputFechaEmision" name="f_emision" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Fecha Contable</label>
                                    <input type="date" class="form-control form-control-sm" id="inputFechaContable" name="f_contabilidad" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold mb-1">Observaciones</label>
                                    <input type="text" class="form-control form-control-sm" id="inputObservaciones" name="observaciones" placeholder="Detalle o referencia del pago...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: MEDIOS DE PAGO -->
                    <div class="card border mb-3 shadow-none">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-primary small text-uppercase">
                                <i class="fas fa-wallet me-1"></i> 2. Medios de Pago Salientes
                            </h6>
                            <button type="button" class="btn btn-xs btn-primary" id="btnAgregarMedio">
                                <i class="fas fa-plus me-1"></i> Agregar Medio
                            </button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-1" id="tablaMedios">
                                    <thead class="table-light">
                                        <tr class="small">
                                            <th style="width: 200px;">Medio de Pago</th>
                                            <th>Cuenta Origen / Chequera / Cheque Endosado / Detalle</th>
                                            <th class="text-end" style="width: 140px;">Importe ($)</th>
                                            <th class="text-center" style="width: 50px;"><i class="fas fa-trash"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyMedios">
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="2" class="text-end">TOTAL FONDOS / VALORES EMITIDOS:</td>
                                            <td class="text-end text-success fs-6" id="totalMediosDisplay">$ 0,00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: COMPROBANTES PENDIENTES DEL PROVEEDOR -->
                    <div class="card border mb-3 shadow-none">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-primary small text-uppercase">
                                <i class="fas fa-receipt me-1"></i> 3. Facturas y Comprobantes a Cancelar
                            </h6>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnAutoImputar" title="Imputar automáticamente por fecha de vencimiento más antigua">
                                    <i class="fas fa-magic me-1"></i> Auto Imputar
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnLimpiarImputaciones" title="Limpiar todos los montos imputados">
                                    <i class="fas fa-eraser me-1"></i> Limpiar
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-2">
                            <div id="spinnerPendientes" class="text-center py-3 d-none">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2 small text-muted">Cargando comprobantes pendientes del proveedor...</span>
                            </div>
                            <div id="vacioPendientes" class="alert alert-light border small text-muted text-center py-2 mb-1">
                                Seleccione un proveedor para ver sus facturas pendientes de pago.
                            </div>
                            <div class="table-responsive d-none" id="contenedorTablaPendientes">
                                <table class="table table-sm table-bordered align-middle mb-1" id="tablaPendientes">
                                    <thead class="table-light">
                                        <tr class="small">
                                            <th class="text-center" style="width: 40px;">
                                                <input class="form-check-input" type="checkbox" id="checkTodosPendientes">
                                            </th>
                                            <th style="width: 140px;">Comprobante</th>
                                            <th style="width: 100px;">Emisión</th>
                                            <th style="width: 100px;">Vto.</th>
                                            <th class="text-end" style="width: 120px;">Total</th>
                                            <th class="text-end" style="width: 120px;">Pendiente</th>
                                            <th class="text-end" style="width: 140px;">Importe a Imputar</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyPendientes">
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="6" class="text-end">TOTAL IMPUTADO A COMPROBANTES:</td>
                                            <td class="text-end text-primary fs-6" id="totalImputadoDisplay">$ 0,00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 4: RESUMEN DE BALANCE Y ANTICIPO (D6) -->
                    <div class="card border border-2 shadow-sm mb-2" id="cardBalance">
                        <div class="card-body py-2">
                            <div class="row align-items-center text-center">
                                <div class="col-md-4 border-end">
                                    <span class="small text-muted text-uppercase fw-bold">Total Pagado</span>
                                    <h5 class="mb-0 fw-bold text-success" id="resumenTotalMedios">$ 0,00</h5>
                                </div>
                                <div class="col-md-4 border-end">
                                    <span class="small text-muted text-uppercase fw-bold">Total Imputado</span>
                                    <h5 class="mb-0 fw-bold text-primary" id="resumenTotalImputado">$ 0,00</h5>
                                </div>
                                <div class="col-md-4">
                                    <span class="small text-muted text-uppercase fw-bold" id="labelDiferencia">Saldo Anticipo</span>
                                    <h5 class="mb-0 fw-bold text-info" id="resumenDiferencia">$ 0,00</h5>
                                </div>
                            </div>
                            <div id="alertaDesbalance" class="alert alert-danger py-1 px-2 small mb-0 mt-2 d-none">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                El total imputado supera el total pagado. Ajuste las imputaciones o agregue medios de pago.
                            </div>
                            <div id="avisoAnticipo" class="alert alert-info py-1 px-2 small mb-0 mt-2 d-none">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Pago a cuenta / Anticipo:</strong> El excedente no aplicado quedará como saldo a favor de la empresa para futuras imputaciones (Regla D6 / RT02).
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnGuardarBorrador">
                    <i class="fas fa-save me-1"></i> Guardar Borrador
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btnConfirmarEmision">
                    <i class="fas fa-check-circle me-1"></i> Confirmar y Emitir OP
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE VISUALIZACIÓN DE ORDEN DE PAGO -->
<div class="modal fade" id="modalVerPago" tabindex="-1" aria-labelledby="modalVerPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalVerPagoLabel">
                    <i class="fas fa-file-invoice me-2"></i>Detalle de Orden de Pago
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="cuerpoPagoImpresion">
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-dark btn-sm" id="btnImprimirPago">
                    <i class="fas fa-print me-1"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE ANULACIÓN CON MOTIVO OBLIGATORIO (§2.6 / RT08) -->
<div class="modal fade" id="modalAnular" tabindex="-1" aria-labelledby="modalAnularLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalAnularLabel">
                    <i class="fas fa-ban me-2"></i>Anular Orden de Pago
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAnular">
                <div class="modal-body p-3">
                    <input type="hidden" id="anular_pago_id" value="0">
                    <div class="alert alert-warning py-2 small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Esta acción anulará la Orden de Pago, revertirá las salidas en el libro de tesorería,
                        reabrirá las facturas de proveedores canceladas, devolverá los cheques de terceros a cartera y anulará el asiento contable.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Motivo de Anulación <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="motivoAnulacion" rows="3" required placeholder="Indique la razón por la cual se anula esta Orden de Pago..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btnConfirmarAnulacion">
                        <i class="fas fa-ban me-1"></i> Proceder con la Anulación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    if (typeof Swal === 'undefined') {
        document.write('<script src="<?= asset_local('js/sweetalert2.all.min.js') ?>"><\/script>');
    }
</script>

<!-- Scripts específicos del módulo -->
<script>
    const PAGINA_ID = <?= $pagina_idx ?>;
    const EMPRESA_ID = typeof window.EMPRESA_ID !== 'undefined' ? window.EMPRESA_ID : <?= intval($_SESSION['empresa_id'] ?? 6) ?>;
    window.EMPRESA_ID = EMPRESA_ID;
</script>
<script src="pagos.js?v=<?= filemtime(__DIR__ . '/pagos.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
