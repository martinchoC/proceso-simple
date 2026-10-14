<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Volantes de Pago Sindical";
$currentPage = 'volantes';
$modudo_idx = 6;
$pagina_idx = 1030;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-barcode me-2 text-primary"></i>Volantes de Pago
                    </h3>
                    <small class="text-muted">Emisión y recaudación de aportes sindicales con código de barras BNA Code128 e intereses resarcitorios a dos fechas</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Volantes de Pago</li>
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
                                <div class="col-md-6">
                                    <label for="filtroEmpleador" class="form-label small fw-semibold text-muted mb-1">Filtrar por Empleador</label>
                                    <select class="form-select form-select-sm select2" id="filtroEmpleador">
                                        <option value="">-- Todos los empleadores autorizados --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Volantes -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <div id="contenedor-boton-agregar" class="d-inline">
                                <button type="button" class="btn btn-primary btn-sm" id="btnNuevoVolante">
                                    <i class="fas fa-plus-circle me-1"></i> Generar Volante de Pago
                                </button>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargar" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="tablaVolantes" class="table table-hover table-striped align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Número</th>
                                        <th>Empleador</th>
                                        <th>Emisión</th>
                                        <th>1° Vencimiento</th>
                                        <th>Total 1° Vto</th>
                                        <th>2° Vencimiento</th>
                                        <th>Total 2° Vto</th>
                                        <th>Código de Barras</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
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
<!-- Modal: Generar Volante de Pago -->
<div class="modal fade" id="modalNuevoVolante" tabindex="-1" aria-labelledby="modalNuevoVolanteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalNuevoVolanteLabel">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Generar Volante de Pago
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formNuevoVolante">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="modalEmpleadorId" class="form-label fw-bold">Empleador <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="modalEmpleadorId" required>
                                <option value="">-- Seleccionar Empleador --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="modalFechaPago1" class="form-label fw-bold">1° Vencimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="modalFechaPago1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="modalFechaPago2" class="form-label fw-bold">2° Vencimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="modalFechaPago2" required>
                        </div>
                    </div>

                    <!-- Deudas Pendientes -->
                    <div class="card border mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <span class="fw-bold text-secondary">
                                <i class="fas fa-list-check me-2"></i>Deudas Pendientes de Pago
                            </span>
                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" id="checkTodos">
                                <label class="form-check-label small fw-semibold" for="checkTodos">Seleccionar todas</label>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 280px;">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaDeudasPendientes">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="40" class="text-center">#</th>
                                            <th>Comprobante</th>
                                            <th>Concepto</th>
                                            <th>Período</th>
                                            <th>Vencimiento</th>
                                            <th class="text-end">Importe Total</th>
                                            <th class="text-end">Saldo Pendiente</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyDeudasPendientes">
                                        <tr>
                                            <td colspan="7" class="text-center py-3 text-muted">
                                                Seleccione un empleador para cargar sus deudas pendientes
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de Liquidación a dos fechas -->
                    <div class="card bg-light border-primary border-opacity-25 mb-3">
                        <div class="card-body py-3">
                            <div class="row text-center">
                                <div class="col-md-6 border-end">
                                    <span class="badge bg-primary px-3 py-1 mb-2">1° VENCIMIENTO</span>
                                    <div class="small text-muted mb-1">Fecha: <span class="fw-bold" id="lblFecha1">-</span></div>
                                    <div class="d-flex justify-content-center gap-3 small text-muted">
                                        <div>Capital: <span id="lblCap1" class="fw-semibold">$0,00</span></div>
                                        <div>Interés: <span id="lblInt1" class="fw-semibold text-danger">$0,00</span></div>
                                    </div>
                                    <h4 class="text-primary fw-bold mt-2 mb-0" id="lblTotal1">$0,00</h4>
                                </div>
                                <div class="col-md-6">
                                    <span class="badge bg-secondary px-3 py-1 mb-2">2° VENCIMIENTO</span>
                                    <div class="small text-muted mb-1">Fecha: <span class="fw-bold" id="lblFecha2">-</span></div>
                                    <div class="d-flex justify-content-center gap-3 small text-muted">
                                        <div>Capital: <span id="lblCap2" class="fw-semibold">$0,00</span></div>
                                        <div>Interés: <span id="lblInt2" class="fw-semibold text-danger">$0,00</span></div>
                                    </div>
                                    <h4 class="text-dark fw-bold mt-2 mb-0" id="lblTotal2">$0,00</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modalObservaciones" class="form-label small text-muted">Observaciones del volante (opcional)</label>
                        <input type="text" class="form-control form-control-sm" id="modalObservaciones" placeholder="ej: Pago de aportes atrasados">
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarEmision" disabled>
                    <i class="fas fa-barcode me-1"></i> Emitir Volante de Pago
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ver e Imprimir Volante de Pago -->
<div class="modal fade" id="modalVerVolante" tabindex="-1" aria-labelledby="modalVerVolanteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title mb-0" id="modalVerVolanteLabel">
                    <i class="fas fa-barcode me-2"></i>Volante Oficial de Pago
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="contenedorImpresionVolante">
                <!-- Plantilla de Volante para Pantalla e Impresión -->
                <div class="border p-4 bg-white rounded shadow-sm" style="font-family: sans-serif;">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="fw-bold text-primary mb-1">CENTRO EMPLEADOS DE COMERCIO</h4>
                            <div class="small text-muted">Filial Carmen de Patagones - CCT 130/75</div>
                            <div class="small text-muted">Personería Gremial N° 340 - CUIT 30-56789012-3</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark border fs-6 px-3 py-2 fw-bold" id="printNroVolante">VOL-00000000</span>
                            <div class="small text-muted mt-1">Emisión: <span id="printFechaEmision">-</span></div>
                        </div>
                    </div>

                    <!-- Datos del Empleador -->
                    <div class="row g-2 mb-3 bg-light p-3 rounded">
                        <div class="col-md-8">
                            <span class="text-muted small">Razón Social:</span>
                            <div class="fw-bold" id="printRazonSocial">-</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">CUIT:</span>
                            <div class="fw-bold" id="printCuit">-</div>
                        </div>
                    </div>

                    <!-- Detalle de Deudas -->
                    <h6 class="fw-bold text-secondary mb-2">Detalle de Conceptos y Deudas Incluidas</h6>
                    <table class="table table-sm table-bordered small align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>Comprobante</th>
                                <th>Concepto</th>
                                <th>Período</th>
                                <th class="text-end">Capital</th>
                                <th class="text-end">Int. 1° Vto</th>
                                <th class="text-end">Subtotal 1</th>
                                <th class="text-end">Int. 2° Vto</th>
                                <th class="text-end">Subtotal 2</th>
                            </tr>
                        </thead>
                        <tbody id="printTbodyDetalle"></tbody>
                    </table>

                    <!-- Talones de Pago con Código de Barras -->
                    <div class="border-top border-2 border-dashed pt-3">
                        <div class="row g-3">
                            <!-- Talón 1 -->
                            <div class="col-md-6 border-end">
                                <div class="p-2 border rounded bg-light text-center">
                                    <div class="small fw-bold text-primary mb-1">TALÓN 1° VENCIMIENTO</div>
                                    <div class="small text-muted">Páguese hasta: <b id="printVto1">-</b></div>
                                    <h4 class="fw-bold text-primary my-2" id="printImporte1">$0,00</h4>
                                    <div class="small text-muted mb-2">Bancos y Canales Habilitados: BNA, Red Link, Interbanking</div>
                                </div>
                            </div>
                            <!-- Talón 2 -->
                            <div class="col-md-6">
                                <div class="p-2 border rounded bg-light text-center">
                                    <div class="small fw-bold text-dark mb-1">TALÓN 2° VENCIMIENTO</div>
                                    <div class="small text-muted">Páguese hasta: <b id="printVto2">-</b></div>
                                    <h4 class="fw-bold text-dark my-2" id="printImporte2">$0,00</h4>
                                    <div class="small text-muted mb-2">Bancos y Canales Habilitados: BNA, Red Link, Interbanking</div>
                                </div>
                            </div>
                        </div>

                        <!-- Código de Barras BNA -->
                        <div class="text-center mt-4 pt-2 bg-light p-3 rounded">
                            <div class="small text-muted mb-1">Código de Barras BNA Code128 para Cobro por Ventanilla:</div>
                            <svg id="barcodeSvg" class="my-2" style="max-width: 100%; height: 75px;"></svg>
                            <div class="font-monospace small fw-bold tracking-wider" id="printCodigoBarrasStr">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir Volante
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JsBarcode para renderizado vectorial del código de barras -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="volantes.js"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
