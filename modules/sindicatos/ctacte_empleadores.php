<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Cuenta Corriente de Empleadores";
$currentPage = 'ctacte_empleadores';
$modudo_idx = 6;
$pagina_idx = 1032;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-money-check-alt me-2 text-primary"></i>Cuenta Corriente Sindical
                    </h3>
                    <small class="text-muted">Estado de cuenta corriente, deudas devengadas, notas de débito de intereses y cobranzas imputadas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Cuenta Corriente</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtro Empleador -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-6">
                                    <label for="selectEmpleador" class="form-label small fw-semibold text-muted mb-1">Seleccionar Empleador <span class="text-danger">*</span></label>
                                    <select class="form-select select2" id="selectEmpleador">
                                        <option value="">-- Seleccionar Empleador --</option>
                                    </select>
                                </div>
                                <div class="col-md-6 text-md-end mt-md-4">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnImprimirCtacte" disabled>
                                        <i class="fas fa-print me-1"></i> Imprimir Resumen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fichas de Totales Consolidados -->
            <div class="row g-3 mb-4" id="seccionTotales" style="display: none;">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="small text-muted fw-semibold mb-1">TOTAL DEVENGADO (DÉBITOS)</div>
                            <h3 class="fw-bold text-danger mb-0" id="cardTotalDebe">$0,00</h3>
                            <small class="text-muted">DDJJ + Notas de Débito</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="small text-muted fw-semibold mb-1">TOTAL CANCELADO (CRÉDITOS)</div>
                            <h3 class="fw-bold text-success mb-0" id="cardTotalHaber">$0,00</h3>
                            <small class="text-muted">Cobranzas + Reversiones</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 bg-primary text-white">
                        <div class="card-body p-3">
                            <div class="small text-white-50 fw-semibold mb-1">SALDO EXIGIBLE ACTUAL</div>
                            <h3 class="fw-bold mb-0 text-white" id="cardSaldoActual">$0,00</h3>
                            <small class="text-white-50">Deuda neta acumulada</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Movimientos -->
            <div class="row" id="seccionTablaMovimientos">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tablaCtacte" class="table table-hover table-striped align-middle w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Comprobante</th>
                                            <th>Concepto / Tipo</th>
                                            <th>Vencimiento</th>
                                            <th class="text-end">Débito (+)</th>
                                            <th class="text-end">Crédito (-)</th>
                                            <th class="text-end">Saldo Acumulado</th>
                                            <th class="text-end">Saldo Pendiente</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCtacte">
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                Seleccione un empleador para visualizar su cuenta corriente
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
<script src="ctacte_empleadores.js"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
