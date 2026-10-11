<?php
/**
 * obligaciones_rendiciones.php
 *
 * Módulo de Obligaciones Impositivas (I_LIQ, I_PAG) y Rendición de Gastos (RG_ANT, RG_REND, RG_REEM)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Obligaciones Impositivas y Rendiciones";
$currentPage = 'obligaciones_rendiciones';
$modudo_idx = 5; // Typo intencional y consistente del ERP
$pagina_idx = 609;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice text-primary me-2"></i>Obligaciones Fiscales y Rendiciones
                    </h3>
                    <small class="text-muted">Liquidaciones de impuestos (I_LIQ/I_PAG), anticipos de viáticos y rendición de comprobantes de gastos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="#">Tesorería</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Obligaciones y Rendiciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs nav-tabs-bordered mb-4" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold py-2 px-3" id="tab-impuestos-tab" data-bs-toggle="tab" data-bs-target="#tab-impuestos" type="button" role="tab" aria-selected="true">
                        <i class="fas fa-landmark text-primary me-2"></i>Obligaciones Impositivas y VEPs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold py-2 px-3" id="tab-rendiciones-tab" data-bs-toggle="tab" data-bs-target="#tab-rendiciones" type="button" role="tab" aria-selected="false">
                        <i class="fas fa-receipt text-success me-2"></i>Rendición de Gastos y Anticipos
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="mainTabsContent">
                <!-- TAB 1: OBLIGACIONES IMPOSITIVAS -->
                <div class="tab-pane fade show active" id="tab-impuestos" role="tabpanel" aria-labelledby="tab-impuestos-tab">
                    <div class="card card-outline card-primary shadow-sm mb-4">
                        <div class="card-header bg-light py-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="card-title mb-0 fw-bold">
                                    <i class="fas fa-file-invoice-dollar text-secondary me-2"></i>Liquidaciones Impositivas (I_LIQ / I_PAG)
                                </h5>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarImp" title="Actualizar">
                                        <i class="fas fa-sync-alt me-1"></i> Actualizar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" id="btnNuevaObligacion">
                                        <i class="fas fa-plus me-1"></i> Nueva Obligación / VEP
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Filtros Impuestos -->
                        <div class="card-body bg-light border-bottom py-2">
                            <form id="formFiltrosImp" class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Período Fiscal (AAAA-MM)</label>
                                    <input type="text" class="form-control form-control-sm" id="filtroPeriodo" placeholder="Ej: 2026-10">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Vto Desde</label>
                                    <input type="date" class="form-control form-control-sm" id="filtroVtoDesde">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Vto Hasta</label>
                                    <input type="date" class="form-control form-control-sm" id="filtroVtoHasta">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Estado</label>
                                    <select class="form-select form-select-sm" id="filtroEstadoImp">
                                        <option value="">-- Todos --</option>
                                        <option value="5">Confirmada</option>
                                        <option value="38">Anulada</option>
                                    </select>
                                </div>
                                <div class="col-md-1 d-grid">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnFiltrarImp">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tabla Impuestos -->
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaObligaciones" class="table table-sm table-striped table-hover align-middle w-100">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 120px;">N° Comprobante</th>
                                            <th style="width: 90px;">Período</th>
                                            <th>Impuesto / Concepto</th>
                                            <th>Organismo</th>
                                            <th style="width: 100px;">Vencimiento</th>
                                            <th style="width: 130px;">N° VEP</th>
                                            <th style="width: 120px;" class="text-end">Total</th>
                                            <th style="width: 120px;" class="text-end">Pendiente</th>
                                            <th style="width: 95px;">Estado</th>
                                            <th style="width: 90px;" class="text-center">Acciones</th>
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

                <!-- TAB 2: RENDICIONES DE GASTOS -->
                <div class="tab-pane fade" id="tab-rendiciones" role="tabpanel" aria-labelledby="tab-rendiciones-tab">
                    <div class="card card-outline card-success shadow-sm mb-4">
                        <div class="card-header bg-light py-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="card-title mb-0 fw-bold">
                                    <i class="fas fa-receipt text-secondary me-2"></i>Rendiciones de Gastos Operativos (RG_REND / RG_ANT)
                                </h5>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarRend" title="Actualizar">
                                        <i class="fas fa-sync-alt me-1"></i> Actualizar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnNuevoAnticipo">
                                        <i class="fas fa-hand-holding-usd me-1"></i> Entregar Anticipo
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" id="btnNuevaRendicion">
                                        <i class="fas fa-plus me-1"></i> Nueva Rendición de Gastos
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla Rendiciones -->
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table id="tablaRendiciones" class="table table-sm table-striped table-hover align-middle w-100">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 120px;">N° Rendición</th>
                                            <th style="width: 90px;">Fecha</th>
                                            <th>Responsable / Empleado</th>
                                            <th>Descripción / Motivo</th>
                                            <th style="width: 120px;" class="text-end">Gastos Totales</th>
                                            <th style="width: 120px;" class="text-end">Anticipo Aplicado</th>
                                            <th style="width: 120px;" class="text-end">A Reembolsar</th>
                                            <th style="width: 95px;">Estado</th>
                                            <th style="width: 110px;" class="text-center">Acciones</th>
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
        </div>
    </div>
</main>

<!-- Modal: Nueva Obligación Impositiva (I_LIQ) -->
<div class="modal fade" id="modalNuevaObligacion" tabindex="-1" aria-labelledby="modalNuevaObligacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNuevaObligacionLabel">
                    <i class="fas fa-landmark me-2"></i>Registrar Liquidación Impositiva / DDJJ (I_LIQ)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevaObligacion">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Organismo Recaudador <span class="text-danger">*</span></label>
                            <select class="form-select" id="liqOrganismo" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Período Fiscal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="liqPeriodo" value="<?php echo date('Y-m'); ?>" placeholder="AAAA-MM" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Fecha Vencimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="liqVto" value="<?php echo date('Y-m-d', strtotime('+10 days')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Descripción del Impuesto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="liqDescripcion" placeholder="Ej: IVA Posición Mensual, IIBB BsAs, Cargas Sociales F931" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">N° VEP</label>
                            <input type="text" class="form-control" id="liqVep" placeholder="Ej: 987654321">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">N° DDJJ / Formulario</label>
                            <input type="text" class="form-control" id="liqDdjj" placeholder="Ej: F.2002">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Importe Total Liquidado <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control fw-bold fs-5 text-primary" id="liqImporte" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sucursal</label>
                            <select class="form-select" id="liqSucursal"></select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarObligacion">
                    <i class="fas fa-check me-1"></i> Registrar Obligación Fiscal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Pagar Obligación Impositiva (I_PAG) -->
<div class="modal fade" id="modalPagarObligacion" tabindex="-1" aria-labelledby="modalPagarObligacionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalPagarObligacionLabel">
                    <i class="fas fa-credit-card me-2"></i>Registrar Pago de Impuesto / VEP (I_PAG)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pagarObligacionId">
                <div class="alert alert-light border mb-3">
                    <div><strong>Obligación:</strong> <span id="pagarDesc">-</span></div>
                    <div><strong>N° Comprobante:</strong> <span id="pagarNro">-</span></div>
                    <div><strong>N° VEP:</strong> <span id="pagarVep">-</span></div>
                    <div class="mt-2"><strong>Saldo Pendiente:</strong> <span id="pagarPendiente" class="text-danger fw-bold fs-5">-</span></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Cuenta de Tesorería (Banco / Caja) <span class="text-danger">*</span></label>
                    <select class="form-select" id="pagarCuentaId" required></select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fecha de Pago <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="pagarFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Importe a Pagar <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0.01" class="form-control fw-bold fs-5 text-success" id="pagarImporte" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">N° Comprobante / Transacción Bancaria</label>
                    <input type="text" class="form-control" id="pagarNroOperacion" placeholder="Ej: TRX-884920">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarPagoImp">
                    <i class="fas fa-check me-1"></i> Confirmar Pago
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Anticipo a Rendir (RG_ANT) -->
<div class="modal fade" id="modalNuevoAnticipo" tabindex="-1" aria-labelledby="modalNuevoAnticipoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalNuevoAnticipoLabel">
                    <i class="fas fa-hand-holding-usd me-2"></i>Entregar Anticipo a Rendir (RG_ANT)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoAnticipo">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Responsable / Empleado <span class="text-danger">*</span></label>
                        <select class="form-select" id="antResponsable" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cuenta de Origen (Caja / Banco) <span class="text-danger">*</span></label>
                        <select class="form-select" id="antCuenta" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha de Entrega <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="antFecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Importe del Anticipo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" class="form-control fw-bold fs-5 text-success" id="antImporte" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo / Descripción</label>
                        <input type="text" class="form-control" id="antDescripcion" placeholder="Ej: Viáticos comisión Bahía Blanca, Fondo fijo de compras">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarAnticipo">
                    <i class="fas fa-check me-1"></i> Entregar Fondos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nueva Rendición de Gastos (RG_REND) -->
<div class="modal fade" id="modalNuevaRendicion" tabindex="-1" aria-labelledby="modalNuevaRendicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalNuevaRendicionLabel">
                    <i class="fas fa-receipt me-2"></i>Registrar Rendición de Gastos y Comprobantes (RG_REND)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevaRendicion">
                    <!-- Cabecera -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Responsable <span class="text-danger">*</span></label>
                            <select class="form-select" id="rendResponsable" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Fecha Rendición <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="rendFecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Anticipo a Descontar (Opcional)</label>
                            <select class="form-select" id="rendAnticipo">
                                <option value="">-- Sin Anticipo Previo --</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Descripción General</label>
                            <input type="text" class="form-control" id="rendDescripcion" placeholder="Ej: Gastos operativos de viaje, compras de insumos menores...">
                        </div>
                    </div>

                    <!-- Detalle de Gastos Dinámicos -->
                    <div class="card bg-light border mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <strong class="text-dark"><i class="fas fa-list me-1"></i> Comprobantes y Gastos Rendidos</strong>
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarFilaGasto">
                                <i class="fas fa-plus me-1"></i> Agregar Comprobante
                            </button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white align-middle" id="tablaDetalleGastos">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 130px;">Fecha Gasto</th>
                                            <th style="width: 180px;">Categoría Gasto</th>
                                            <th>Proveedor / Comercio</th>
                                            <th style="width: 140px;">N° Ticket/Fact</th>
                                            <th style="width: 140px;" class="text-end">Importe ($)</th>
                                            <th style="width: 45px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyDetalleGastos">
                                        <!-- Filas dinámicas -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de Balance de Rendición -->
                    <div class="row g-2 justify-content-end">
                        <div class="col-md-4">
                            <div class="p-3 bg-light border rounded">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Gastos:</span>
                                    <strong id="rendTotalGastos">$ 0,00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1 text-secondary">
                                    <span>Anticipo Descontado:</span>
                                    <strong id="rendMontoAnticipo">$ 0,00</strong>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between fs-5 text-success">
                                    <span class="fw-bold">Saldo a Reembolsar:</span>
                                    <strong id="rendSaldoReembolso">$ 0,00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarRendicion">
                    <i class="fas fa-check me-1"></i> Confirmar y Aprobar Rendición
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reembolso de Rendición (RG_REEM) -->
<div class="modal fade" id="modalReembolso" tabindex="-1" aria-labelledby="modalReembolsoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalReembolsoLabel">
                    <i class="fas fa-hand-holding-usd me-2"></i>Pagar Reembolso al Responsable (RG_REEM)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reemRendicionId">
                <div class="alert alert-light border mb-3">
                    <div><strong>Rendición:</strong> <span id="reemNro">-</span></div>
                    <div class="mt-2"><strong>Importe a Reembolsar:</strong> <span id="reemMonto" class="text-success fw-bold fs-5">-</span></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Cuenta de Pago (Caja / Banco) <span class="text-danger">*</span></label>
                    <select class="form-select" id="reemCuentaId" required></select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fecha de Pago <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="reemFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarReembolso">
                    <i class="fas fa-check me-1"></i> Confirmar Reembolso
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

<!-- SweetAlert2 y Script de Fase 6 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="obligaciones_rendiciones.js"></script>
