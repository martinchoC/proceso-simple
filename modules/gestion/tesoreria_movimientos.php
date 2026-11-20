<?php
/**
 * tesoreria_movimientos.php
 *
 * MÃ³dulo de Movimientos Internos de TesorerÃ­a (Transferencias entre Cuentas Propias)
 * Sistema gestion_multipyme - MÃ³dulo TesorerÃ­a
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';

$pageTitle = "Movimientos Internos de TesorerÃ­a";
$currentPage = 'tesoreria_movimientos';
$modudo_idx = 2; // Typo intencional y consistente del ERP
$pagina_idx = 108;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Movimientos internos','fas fa-exchange-alt', 'Transferencias entre Cajas y Cuentas Bancarias de la misma empresa con impacto contable automÃ¡tico', ['TesorerÃ­a']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header bg-light py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="fas fa-list text-secondary me-2"></i>Registro de Transferencias Internas
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar listado">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                            <?php if (permisos_puede($conexion, $pagina_idx, 'agregar')): ?>
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaTransferencia">
                                <i class="fas fa-plus me-1"></i> Nueva Transferencia
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="btnNuevoOtroMovimiento">
                                <i class="fas fa-plus-circle me-1"></i> Otro Ingreso / Egreso
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
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
                            <label class="form-label small fw-bold mb-1">Cuenta Involucrada</label>
                            <select class="form-select form-select-sm" id="filtroCuenta" name="cuenta_id">
                                <option value="">-- Todas las Cuentas --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Estado</label>
                            <select class="form-select form-select-sm" id="filtroEstado" name="estado_id">
                                <option value="">-- Todos --</option>
                                <option value="3">Borrador</option>
                                <option value="4">Pendiente AprobaciÃ³n</option>
                                <option value="5">Confirmado</option>
                                <option value="38">Anulado</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-sm btn-primary" id="btnFiltrar" title="Filtrar">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Solapas y Tablas -->
                <div class="card-body p-3">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tabTransBtn" data-bs-toggle="tab" data-bs-target="#paneTrans" type="button" role="tab">
                                <i class="fas fa-exchange-alt me-1"></i> Transferencias internas
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tabOtrosBtn" data-bs-toggle="tab" data-bs-target="#paneOtros" type="button" role="tab">
                                <i class="fas fa-coins me-1"></i> Otros ingresos / egresos
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="paneTrans" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaTransferencias" class="table table-sm table-striped table-hover align-middle w-100">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 90px;">Fecha</th>
                                            <th style="width: 140px;">Comprobante</th>
                                            <th style="width: 120px;">Sucursal</th>
                                            <th>Cuenta Origen (Egreso)</th>
                                            <th>Cuenta Destino (Ingreso)</th>
                                            <th style="width: 130px;" class="text-end">Importe</th>
                                            <th>Observaciones</th>
                                            <th style="width: 100px;">Estado</th>
                                            <th style="width: 90px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="paneOtros" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaOtrosMovimientos" class="table table-sm table-striped table-hover align-middle w-100">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 90px;">Fecha</th>
                                            <th style="width: 145px;">Comprobante</th>
                                            <th style="width: 85px;">Tipo</th>
                                            <th>Entidad / Socio</th>
                                            <th>Cuenta TesorerÃ­a</th>
                                            <th style="width: 120px;" class="text-end">Importe</th>
                                            <th>Concepto</th>
                                            <th style="width: 115px;">Estado</th>
                                            <th style="width: 165px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal: Nueva Transferencia Interna -->
<div class="modal fade" id="modalTransferencia" tabindex="-1" aria-labelledby="modalTransferenciaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTransferenciaLabel">
                    <i class="fas fa-exchange-alt me-2"></i>Nueva Transferencia Interna entre Cuentas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formTransferencia">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha de Movimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="transFecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sucursal Operativa <span class="text-danger">*</span></label>
                            <select class="form-select" id="transSucursal" required>
                                <!-- DinÃ¡mico -->
                            </select>
                        </div>

                        <!-- Origen y Destino -->
                        <div class="col-md-6">
                            <div class="card bg-light border-danger border-2 h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-danger fw-bold mb-3">
                                        <i class="fas fa-arrow-circle-up me-1"></i> Cuenta de Origen (Egreso)
                                    </h6>
                                    <div class="mb-2">
                                        <select class="form-select" id="transOrigen" required>
                                            <option value="">-- Seleccionar Cuenta Origen --</option>
                                        </select>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2 p-2 bg-white rounded border">
                                        <span class="small text-muted">Saldo Disponible:</span>
                                        <span class="fw-bold text-dark fs-6" id="transSaldoOrigen">$ 0,00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card bg-light border-success border-2 h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-success fw-bold mb-3">
                                        <i class="fas fa-arrow-circle-down me-1"></i> Cuenta de Destino (Ingreso)
                                    </h6>
                                    <div class="mb-2">
                                        <select class="form-select" id="transDestino" required>
                                            <option value="">-- Seleccionar Cuenta Destino --</option>
                                        </select>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2 p-2 bg-white rounded border">
                                        <span class="small text-muted">Saldo Actual:</span>
                                        <span class="fw-bold text-dark fs-6" id="transSaldoDestino">$ 0,00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Importe -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Importe a Transferir <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light fw-bold">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control fw-bold fs-4 text-primary" id="transImporte" placeholder="0.00" required>
                            </div>
                            <div id="alertaSaldoInsuficiente" class="text-danger small mt-1 d-none">
                                <i class="fas fa-exclamation-triangle me-1"></i> El importe supera el saldo disponible de la cuenta de origen.
                            </div>
                        </div>

                        <!-- Observaciones / Motivo -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Concepto / Motivo de la Transferencia</label>
                            <input type="text" class="form-control" id="transObservaciones" placeholder="Ej: ReposiciÃ³n de fondo fijo, depÃ³sito de recaudaciÃ³n diaria, traspaso bancario...">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarTransferencia">
                    <i class="fas fa-check me-1"></i> Confirmar y Registrar Movimiento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Otro Ingreso / Egreso contra Cuenta Contable -->
<div class="modal fade" id="modalOtroMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-coins me-2"></i>Otro Ingreso / Egreso de TesorerÃ­a</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formOtroMovimiento">
                    <input type="hidden" id="otroComprobanteId" value="0">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select" id="otroTipo" required>
                                <option value="INGRESO">INGRESO (entra a banco/caja)</option>
                                <option value="EGRESO">EGRESO (sale de banco/caja)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="otroFecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Sucursal <span class="text-danger">*</span></label>
                            <select class="form-select" id="otroSucursal" required></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Socio / Entidad <span class="text-muted small fw-normal">(opcional â€” recomendado para aportes de socios)</span></label>
                            <select class="form-select" id="otroEntidad">
                                <option value="0">-- Sin entidad especÃ­fica --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cuenta de TesorerÃ­a (Banco / Caja) <span class="text-danger">*</span></label>
                            <select class="form-select" id="otroCuentaTes" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cuenta Contable Contrapartida <span class="text-danger">*</span></label>
                            <select class="form-select" id="otroCuentaCont" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control fw-bold" id="otroImporte" placeholder="0.00" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Concepto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="otroConcepto" placeholder="Ej: Aporte de socio, prÃ©stamo bancario, rendimiento...">
                        </div>
                    </div>
                    <div class="alert alert-light border small mt-3 mb-0 py-2">
                        <i class="fas fa-info-circle text-primary me-1"></i>
                        El comprobante soporte (<strong>MC</strong>) se genera primero en <strong>Borrador</strong> o <strong>Pendiente de AprobaciÃ³n</strong> sin mover caja ni contabilidad hasta ser confirmado/aprobado.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-primary" id="btnGuardarOtroBorrador">
                    <i class="fas fa-save me-1"></i> Guardar Borrador
                </button>
                <button type="button" class="btn btn-success" id="btnConfirmarOtroMovimiento">
                    <i class="fas fa-paper-plane me-1"></i> Guardar y Enviar a AprobaciÃ³n
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

<!-- SweetAlert2 y Script de Movimientos -->
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="tesoreria_movimientos.js?v=<?= @filemtime(__DIR__ . '/tesoreria_movimientos.js') ?>"></script>
