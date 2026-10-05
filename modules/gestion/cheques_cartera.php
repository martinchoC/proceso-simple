<?php
/**
 * cheques_cartera.php
 *
 * Módulo de Cartera de Cheques y Conciliación Bancaria
 * Sistema gestion_multipyme - Módulo Tesorería
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Cartera de Cheques y Conciliación";
$currentPage = 'cheques_cartera';
$modudo_idx = 2; // Typo intencional del ERP
$pagina_idx = 107;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-money-check text-primary me-2"></i>Cartera de Cheques y Bancos
                    </h3>
                    <small class="text-muted">Administración integral de cheques de terceros, cheques propios diferidos, depósitos y débitos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="#">Tesorería</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Cartera de Cheques</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- 4 Widgets Resumen -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="info-box bg-white shadow-sm border-start border-success border-4 h-100">
                        <span class="info-box-icon bg-success-subtle text-success"><i class="fas fa-wallet fa-lg"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted fw-bold text-uppercase">Terceros en Cartera</span>
                            <span class="info-box-number fs-4 text-dark" id="widgetCarteraMonto">$ 0,00</span>
                            <div class="progress progress-xs">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <span class="progress-description text-muted small mt-1" id="widgetCarteraCant">0 cheques disponibles</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="info-box bg-white shadow-sm border-start border-warning border-4 h-100">
                        <span class="info-box-icon bg-warning-subtle text-warning"><i class="fas fa-university fa-lg"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted fw-bold text-uppercase">Depositados a Acreditar</span>
                            <span class="info-box-number fs-4 text-dark" id="widgetDepositadosMonto">$ 0,00</span>
                            <div class="progress progress-xs">
                                <div class="progress-bar bg-warning" style="width: 100%"></div>
                            </div>
                            <span class="progress-description text-muted small mt-1" id="widgetDepositadosCant">0 cheques en clearing</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="info-box bg-white shadow-sm border-start border-primary border-4 h-100">
                        <span class="info-box-icon bg-primary-subtle text-primary"><i class="fas fa-file-signature fa-lg"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted fw-bold text-uppercase">Propios Diferidos / Emitidos</span>
                            <span class="info-box-number fs-4 text-dark" id="widgetPropiosMonto">$ 0,00</span>
                            <div class="progress progress-xs">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                            <span class="progress-description text-muted small mt-1" id="widgetPropiosCant">0 cheques emitidos</span>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="info-box bg-white shadow-sm border-start border-danger border-4 h-100">
                        <span class="info-box-icon bg-danger-subtle text-danger"><i class="fas fa-ban fa-lg"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted fw-bold text-uppercase">Rechazados / Devueltos</span>
                            <span class="info-box-number fs-4 text-danger" id="widgetRechazadosMonto">$ 0,00</span>
                            <div class="progress progress-xs">
                                <div class="progress-bar bg-danger" style="width: 100%"></div>
                            </div>
                            <span class="progress-description text-muted small mt-1" id="widgetRechazadosCant">0 cheques observados</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card de Cheques con Filtros y Grilla -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="fas fa-list text-secondary me-2"></i>Listado y Seguimiento de Cheques
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar listado">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card-body bg-light border-bottom py-2">
                    <form id="formFiltros" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Tipo de Cheque</label>
                            <select class="form-select form-select-sm" id="filtroTipo" name="tipo">
                                <option value="">-- Todos --</option>
                                <option value="TERCERO">Terceros</option>
                                <option value="PROPIO">Propios</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Estado</label>
                            <select class="form-select form-select-sm" id="filtroEstado" name="estado_id">
                                <option value="">-- Todos --</option>
                                <option value="2172">En Cartera</option>
                                <option value="2173">Emitido</option>
                                <option value="2174">Depositado</option>
                                <option value="2176">Debitado</option>
                                <option value="2175">Endosado</option>
                                <option value="2178">Rechazado</option>
                                <option value="2179">Anulado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Banco</label>
                            <select class="form-select form-select-sm select2" id="filtroBanco" name="banco_id">
                                <option value="">-- Todos los Bancos --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Vto Desde</label>
                            <input type="date" class="form-control form-control-sm" id="filtroDesde" name="desde">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Vto Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="filtroHasta" name="hasta">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-sm btn-primary" id="btnFiltrar" title="Aplicar Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabla DataTable -->
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaCheques" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 80px;">Tipo</th>
                                    <th style="width: 100px;">N° Cheque</th>
                                    <th>Banco</th>
                                    <th>Titular / CUIT</th>
                                    <th style="width: 90px;">F. Emisión</th>
                                    <th style="width: 90px;">F. Cobro</th>
                                    <th style="width: 120px;" class="text-end">Importe</th>
                                    <th>Cuenta Bancaria</th>
                                    <th style="width: 110px;">Estado</th>
                                    <th style="width: 110px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico vía DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal: Depositar Cheque de Tercero -->
<div class="modal fade" id="modalDepositar" tabindex="-1" aria-labelledby="modalDepositarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalDepositarLabel">
                    <i class="fas fa-university me-2"></i>Depositar Cheque de Tercero
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="depositoChequeId">
                <div class="alert alert-light border mb-3">
                    <div class="row g-2">
                        <div class="col-6"><strong>N° Cheque:</strong> <span id="depositoNumero">-</span></div>
                        <div class="col-6"><strong>Banco Emisor:</strong> <span id="depositoBanco">-</span></div>
                        <div class="col-12"><strong>Importe:</strong> <span id="depositoImporte" class="fs-5 text-success fw-bold">-</span></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Cuenta Bancaria Destino <span class="text-danger">*</span></label>
                    <select class="form-select" id="depositoCuentaId" required>
                        <option value="">-- Seleccionar Cuenta Bancaria --</option>
                    </select>
                    <small class="text-muted">El cheque pasará a estado DEPOSITADO acreditando la cuenta bancaria de la empresa.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fecha de Depósito <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="depositoFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarDeposito">
                    <i class="fas fa-check me-1"></i> Confirmar Depósito
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Débito Bancario Cheque Propio -->
<div class="modal fade" id="modalDebitar" tabindex="-1" aria-labelledby="modalDebitarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="modalDebitarLabel">
                    <i class="fas fa-check-circle me-2"></i>Registrar Débito Bancario (Cheque Propio)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="debitoChequeId">
                <div class="alert alert-light border mb-3">
                    <div class="row g-2">
                        <div class="col-6"><strong>N° Cheque:</strong> <span id="debitoNumero">-</span></div>
                        <div class="col-6"><strong>Cuenta:</strong> <span id="debitoCuenta">-</span></div>
                        <div class="col-12"><strong>Importe:</strong> <span id="debitoImporte" class="fs-5 text-primary fw-bold">-</span></div>
                    </div>
                </div>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i> Se generará el asiento contable de débito cancelando <em>Cheques Propios Emitidos</em> y debitando la <em>Cuenta Bancaria</em> respectiva.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fecha de Débito Bancario <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="debitoFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark" id="btnConfirmarDebito">
                    <i class="fas fa-check me-1"></i> Confirmar Débito
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rechazo de Cheque -->
<div class="modal fade" id="modalRechazar" tabindex="-1" aria-labelledby="modalRechazarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalRechazarLabel">
                    <i class="fas fa-ban me-2"></i>Registrar Rechazo de Cheque
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rechazoChequeId">
                <div class="alert alert-light border mb-3">
                    <div class="row g-2">
                        <div class="col-6"><strong>N° Cheque:</strong> <span id="rechazoNumero">-</span></div>
                        <div class="col-6"><strong>Tipo:</strong> <span id="rechazoTipo">-</span></div>
                        <div class="col-12"><strong>Importe:</strong> <span id="rechazoImporte" class="fs-5 text-danger fw-bold">-</span></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fecha de Rechazo <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="rechazoFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Motivo de Rechazo <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rechazoMotivo" rows="3" placeholder="Ej: Sin fondos suficientes, Defectos formales de firma, Orden de no pagar..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarRechazo">
                    <i class="fas fa-ban me-1"></i> Registrar Rechazo
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

<!-- SweetAlert2 & Select2 si hicieran falta -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="cheques_cartera.js"></script>
