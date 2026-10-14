<?php
/**
 * modules/sindicatos/planes.php
 * Vista de Planes de Pago y Refinanciación Sindical (Página 1042).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Planes de Pago y Refinanciación";
$currentPage = 'planes';
$modudo_idx = 6;
$pagina_idx = 1042;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-handshake me-2 text-primary"></i>Planes de Pago y Refinanciación
                    </h3>
                    <small class="text-muted">Acuerdos de facilidades, moratorias y amortización de deudas sindicales y actas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Planes de Pago</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtros Superiores -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted mb-1">Filtrar por Empleador</label>
                                    <select class="form-select form-select-sm" id="filtroEmpleador">
                                        <option value="">-- Todos los empleadores --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                                    <select class="form-select form-select-sm" id="filtroEstado">
                                        <option value="">-- Todos los estados --</option>
                                        <option value="5">Confirmado / Vigente</option>
                                        <option value="39">Cancelado Parcial</option>
                                        <option value="40">Cancelado Total</option>
                                        <option value="38">Anulado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Buscar Número o Razón Social</label>
                                    <input type="text" class="form-control form-control-sm" id="filtroBusqueda" placeholder="Ej: PLAN-2026-..., Comercio">
                                </div>
                                <div class="col-md-2 text-md-end mt-md-4">
                                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevoPlan">
                                        <i class="fas fa-plus-circle me-1"></i> Nuevo Plan
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" id="btnRecargar" title="Recargar lista">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Planes -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="tablaPlanes">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Número</th>
                                            <th>Empleador / CUIT</th>
                                            <th>Régimen</th>
                                            <th>F. Acuerdo</th>
                                            <th class="text-end">Capital Orig.</th>
                                            <th class="text-end">Interés Fin.</th>
                                            <th class="text-end">Total Financiado</th>
                                            <th class="text-center">Cuotas</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-end" style="min-width: 140px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyPlanes">
                                        <tr>
                                            <td colspan="10" class="text-center py-5 text-muted">
                                                <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando planes de pago...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Modal Nuevo Plan con Simulador -->
<div class="modal fade" id="modalNuevoPlan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-handshake me-2"></i>Acuerdo y Simulación de Plan de Pagos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Columna Izquierda: Parámetros del plan -->
                    <div class="col-md-5 border-end pe-3">
                        <h6 class="fw-bold text-primary mb-2">1. Configuración de Financiación</h6>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Empleador *</label>
                            <select class="form-select form-select-sm" id="planEmpleadorId" required>
                                <option value="">-- Seleccionar empleador --</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Régimen / Tipo de Plan *</label>
                            <select class="form-select form-select-sm" id="planTipoId" required>
                                <option value="">-- Seleccionar tipo --</option>
                            </select>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Cantidad Cuotas *</label>
                                <input type="number" class="form-control form-control-sm" id="planCuotas" min="1" max="60" value="6" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Anticipo %</label>
                                <input type="number" step="0.5" class="form-control form-control-sm" id="planAnticipoPct" placeholder="0.00" value="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Fecha 1° Vencimiento</label>
                            <input type="date" class="form-control form-control-sm" id="planPrimerVto">
                        </div>

                        <!-- Deudas a consolidar -->
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="fw-bold text-dark mb-0">Deudas Pendientes a Refinanciar</h6>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnCargarDeudas">
                                <i class="fas fa-sync-alt"></i> Buscar
                            </button>
                        </div>
                        <div class="table-responsive border rounded p-1 mb-2" style="max-height: 180px;">
                            <table class="table table-sm table-hover mb-0 small" id="tablaDeudasPendientes">
                                <thead>
                                    <tr>
                                        <th style="width: 25px;"><input type="checkbox" id="chkSelTodas"></th>
                                        <th>Comprobante</th>
                                        <th>Vto</th>
                                        <th class="text-end">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDeudasPendientes">
                                    <tr><td colspan="4" class="text-muted text-center py-3">Seleccione un empleador</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-3">
                            <span class="small fw-semibold">Capital Consolidado:</span>
                            <strong class="text-primary fs-6" id="lblCapitalConsolidado">$0.00</strong>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" id="btnSimular">
                            <i class="fas fa-calculator me-1"></i> Simular Cronograma de Cuotas
                        </button>

                        <div class="mb-0">
                            <label class="form-label small fw-semibold">Observaciones</label>
                            <textarea class="form-control form-control-sm" id="planObservaciones" rows="2" placeholder="Acuerdo, expediente, resolución..."></textarea>
                        </div>
                    </div>

                    <!-- Columna Derecha: Vista previa de simulación -->
                    <div class="col-md-7 ps-3">
                        <h6 class="fw-bold text-success mb-2">2. Proyección Financiera y Cuotas</h6>
                        
                        <div class="row g-2 mb-3 bg-light p-2 rounded text-center">
                            <div class="col-4">
                                <small class="text-muted d-block">Capital Financiado:</small>
                                <strong id="simCapital">$0.00</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Interés Pactado:</small>
                                <strong class="text-danger" id="simInteres">$0.00</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Total del Plan:</small>
                                <strong class="text-primary fs-6" id="simTotal">$0.00</strong>
                            </div>
                        </div>

                        <div class="table-responsive border rounded" style="max-height: 380px;">
                            <table class="table table-sm table-hover table-bordered text-center align-middle mb-0" id="tablaSimulacion">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Cuota</th>
                                        <th>Fecha Vencimiento</th>
                                        <th class="text-end">Capital</th>
                                        <th class="text-end">Interés</th>
                                        <th class="text-end">Importe Cuota</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodySimulacion">
                                    <tr><td colspan="5" class="py-5 text-muted">Complete los datos y pulse "Simular"</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="btnConfirmarPlan" disabled>
                    <i class="fas fa-check-double me-1"></i> Confirmar y Refinanciar Deuda
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle Plan -->
<div class="modal fade" id="modalDetallePlan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="detallePlanTitulo">
                    <i class="fas fa-search me-2"></i>Detalle de Plan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3 bg-light p-3 rounded">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Empleador:</small>
                        <strong id="detPlanRazonSocial">-</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Régimen:</small>
                        <span class="badge bg-secondary" id="detPlanRegimen">-</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Total Financiado:</small>
                        <strong class="text-primary fs-6" id="detPlanTotal">$0.00</strong>
                    </div>
                    <div class="col-md-2 text-end">
                        <small class="text-muted d-block">Estado:</small>
                        <span class="badge bg-success" id="detPlanEstado">-</span>
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Cuotas y Comprobantes Generados (PLC)</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center align-middle mb-0" id="tablaCuotasDetalle">
                        <thead class="table-light">
                            <tr>
                                <th>Cuota</th>
                                <th>Fecha Vto</th>
                                <th class="text-end">Capital</th>
                                <th class="text-end">Interés</th>
                                <th class="text-end">Importe</th>
                                <th class="text-end">Saldo Pend.</th>
                                <th>Comprobante</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCuotasDetalle">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnAnularPlan">
                        <i class="fas fa-ban me-1"></i> Anular Plan
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="modules/sindicatos/planes.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
