<?php
/**
 * tesoreria_movimientos.php
 *
 * Módulo de Movimientos Internos de Tesorería (Transferencias entre Cuentas Propias)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Movimientos Internos de Tesorería";
$currentPage = 'tesoreria_movimientos';
$modudo_idx = 2; // Typo intencional y consistente del ERP
$pagina_idx = 108;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-exchange-alt text-primary me-2"></i>Movimientos Internos de Tesorería
                    </h3>
                    <small class="text-muted">Transferencias entre Cajas y Cuentas Bancarias de la misma empresa con impacto contable automático</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="#">Tesorería</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Movimientos Internos</li>
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
                            <i class="fas fa-list text-secondary me-2"></i>Registro de Transferencias Internas
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar listado">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaTransferencia">
                                <i class="fas fa-plus me-1"></i> Nueva Transferencia
                            </button>
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
                                <option value="2126">Confirmado</option>
                                <option value="2127">Anulado</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-sm btn-primary" id="btnFiltrar" title="Filtrar">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabla -->
                <div class="card-body p-3">
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
                                <!-- Dinámico -->
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
                            <input type="text" class="form-control" id="transObservaciones" placeholder="Ej: Reposición de fondo fijo, depósito de recaudación diaria, traspaso bancario...">
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

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

<!-- SweetAlert2 y Script de Movimientos -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="tesoreria_movimientos.js"></script>
