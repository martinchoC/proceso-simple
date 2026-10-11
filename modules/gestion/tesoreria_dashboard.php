<?php
/**
 * tesoreria_dashboard.php
 *
 * Dashboard Financiero Combinado de Caja y Bancos
 * Sistema gestion_multipyme - Módulo Tesorería
 * Página ID: 104 | Módulo ID: 2
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Dashboard Financiero - Caja y Bancos";
$currentPage = 'tesoreria_dashboard';
$modudo_idx = 2;
$pagina_idx = 104;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = intval($_SESSION['empresa_id'] ?? 0);

// Obtener sucursales para el selector
$sucursales = [];
$res_suc = mysqli_query($conn, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1 ORDER BY sucursal_nombre ASC");
if ($res_suc) {
    while ($s = mysqli_fetch_assoc($res_suc)) {
        $sucursales[] = $s;
    }
}
?>

<style>
.dashboard-kpi-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 0.75rem;
}
.dashboard-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
.kpi-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.nav-pills-dashboard .nav-link {
    font-weight: 600;
    padding: 0.65rem 1.25rem;
    border-radius: 0.5rem;
    color: #495057;
    transition: all 0.2s ease;
}
.nav-pills-dashboard .nav-link.active {
    background-color: #0d6efd;
    color: #fff;
    box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
}
.table-dashboard th {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}
.badge-subtle-success {
    background-color: #d1e7dd;
    color: #0f5132;
}
.badge-subtle-danger {
    background-color: #f8d7da;
    color: #842029;
}
.badge-subtle-warning {
    background-color: #fff3cd;
    color: #664d03;
}
.badge-subtle-primary {
    background-color: #cfe2ff;
    color: #084298;
}
.badge-subtle-info {
    background-color: #cff4fc;
    color: #055160;
}
</style>

<div class="app-content-header py-3 border-bottom bg-white">
    <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-landmark text-primary me-2"></i>Dashboard Financiero (Caja y Bancos)
                    </h3>
                    <small class="text-muted">Posición consolidada de liquidez, movimientos del período, flujo proyectado y cartera de valores</small>
                </div>
                <div class="col-sm-6 text-end">
                    <div class="d-inline-flex align-items-center gap-2">
                        <!-- Filtro Sucursal -->
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-store"></i></span>
                            <select class="form-select form-select-sm" id="filtroSucursal">
                                <option value="">Todas las Sucursales</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?php echo $suc['sucursal_id']; ?>"><?php echo htmlspecialchars($suc['sucursal_nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnActualizarDashboard" title="Recargar datos">
                            <i class="fas fa-sync-alt me-1"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-3">
        <div class="container-fluid">

            <!-- ============================================================== -->
            <!-- TARJETAS SUPERIORES DE KPIS CONSOLIDADOS                      -->
            <!-- ============================================================== -->
            <div class="row g-3 mb-4">
                <!-- KPI 1: Saldo Consolidado ARS -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-kpi-card border-0 shadow-sm bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Saldo Total Disponible (ARS)</span>
                                <div class="kpi-icon-box bg-primary-subtle text-primary">
                                    <i class="fas fa-wallet fa-lg"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-dark" id="kpiSaldoConsolidado">$ 0,00</h3>
                            <div class="small text-muted d-flex justify-content-between mt-2 pt-2 border-top">
                                <span><i class="fas fa-cash-register me-1 text-success"></i>Cajas: <strong id="kpiSaldoCajas">$ 0,00</strong></span>
                                <span><i class="fas fa-university me-1 text-primary"></i>Bancos: <strong id="kpiSaldoBancos">$ 0,00</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI 2: Disponibilidades USD -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-kpi-card border-0 shadow-sm bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Moneda Extranjera (USD)</span>
                                <div class="kpi-icon-box bg-success-subtle text-success">
                                    <i class="fas fa-dollar-sign fa-lg"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-success" id="kpiSaldoUSD">U$S 0,00</h3>
                            <div class="small text-muted d-flex justify-content-between mt-2 pt-2 border-top">
                                <span>Cotización ref.: <strong id="kpiCotizacionUSD">$ 0,00</strong></span>
                                <span>Equiv. Total: <strong id="kpiConsolidadoTotalARS">$ 0,00</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI 3: Movimientos Netos Período -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-kpi-card border-0 shadow-sm bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Flujo Neto del Período</span>
                                <div class="kpi-icon-box bg-info-subtle text-info">
                                    <i class="fas fa-exchange-alt fa-lg"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1" id="kpiFlujoNeto">$ 0,00</h3>
                            <div class="small text-muted d-flex justify-content-between mt-2 pt-2 border-top">
                                <span><i class="fas fa-arrow-down text-success me-1"></i>Ing: <strong id="kpiTotalIngresos">$ 0,00</strong></span>
                                <span><i class="fas fa-arrow-up text-danger me-1"></i>Egr: <strong id="kpiTotalEgresos">$ 0,00</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI 4: Valores a Depositar (Cartera) -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-kpi-card border-0 shadow-sm bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-uppercase text-muted fw-bold small">Cheques en Cartera</span>
                                <div class="kpi-icon-box bg-warning-subtle text-warning">
                                    <i class="fas fa-money-check fa-lg"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-dark" id="kpiCarteraMonto">$ 0,00</h3>
                            <div class="small text-muted d-flex justify-content-between mt-2 pt-2 border-top">
                                <span id="kpiCarteraCant">0 cheques</span>
                                <span id="kpiCarteraAlertas" class="badge badge-subtle-success">Al día</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerta de Desfasaje Contable (si la auditoría detecta discrepancia) -->
            <div id="alertaDesfasajeContable" class="alert alert-warning border-warning d-none mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading fw-bold mb-1">Aviso de Integridad Contable</h6>
                        <span id="alertaDesfasajeTexto">Se detectó una discrepancia entre el saldo materializado y los asientos del libro de tesorería en una o más cuentas.</span>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- PESTAÑAS PRINCIPALES DEL DASHBOARD                             -->
            <!-- ============================================================== -->
            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <ul class="nav nav-pills nav-pills-dashboard card-header-pills" id="dashboardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-cuentas-btn" data-bs-toggle="pill" data-bs-target="#tab-cuentas" type="button" role="tab">
                                <i class="fas fa-university me-2"></i>1. Cuentas y Saldos Disponibles
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-movimientos-btn" data-bs-toggle="pill" data-bs-target="#tab-movimientos" type="button" role="tab">
                                <i class="fas fa-history me-2"></i>2. Movimientos del Período
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-flujo-btn" data-bs-toggle="pill" data-bs-target="#tab-flujo" type="button" role="tab">
                                <i class="fas fa-chart-line me-2"></i>3. Flujo de Fondos Proyectado
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-valores-btn" data-bs-toggle="pill" data-bs-target="#tab-valores" type="button" role="tab">
                                <i class="fas fa-money-check-alt me-2"></i>4. Valores a Depositar (Cheques)
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content" id="dashboardTabsContent">

                        <!-- ====================================================== -->
                        <!-- PESTAÑA 1: CUENTAS Y SALDOS INDIVIDUALES              -->
                        <!-- ====================================================== -->
                        <div class="tab-pane fade show active" id="tab-cuentas" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Detalle de Cuentas Financieras</h5>
                                    <small class="text-muted">Descubrimiento dinámico por flag <code class="text-primary">es_caja_banco = 1</code> en el Plan de Cuentas Contable</small>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta active" data-tipo="">Todas</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta" data-tipo="CAJA">Cajas</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta" data-tipo="BANCO">Bancos</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta" data-tipo="VALORES">Valores</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard align-middle mb-0" id="tablaCuentas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Cuenta de Tesorería</th>
                                            <th>Cuenta Contable Vinculada</th>
                                            <th>Banco / CBU / Alias</th>
                                            <th>Sucursal</th>
                                            <th class="text-end">Saldo Actual</th>
                                            <th class="text-center">Integridad</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCuentas">
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando cuentas...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ====================================================== -->
                        <!-- PESTAÑA 2: MOVIMIENTOS DEL PERÍODO                    -->
                        <!-- ====================================================== -->
                        <div class="tab-pane fade" id="tab-movimientos" role="tabpanel">
                            <!-- Barra de Filtros del Período -->
                            <div class="p-3 bg-light rounded-3 mb-4">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-auto fw-bold text-muted small">
                                        <i class="fas fa-calendar-alt me-1"></i>PERÍODO:
                                    </div>
                                    <div class="col-6 col-md-auto">
                                        <input type="date" class="form-control form-control-sm" id="movFechaDesde" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-6 col-md-auto">
                                        <input type="date" class="form-control form-control-sm" id="movFechaHasta" value="<?php echo date('Y-m-t'); ?>">
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <select class="form-select form-select-sm" id="movCuentaId">
                                            <option value="">Todas las cuentas</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-preset-fecha" data-preset="hoy">Hoy</button>
                                            <button type="button" class="btn btn-outline-primary btn-preset-fecha" data-preset="semana">Semana</button>
                                            <button type="button" class="btn btn-outline-primary btn-preset-fecha active" data-preset="mes">Este Mes</button>
                                            <button type="button" class="btn btn-outline-primary btn-preset-fecha" data-preset="30d">Últimos 30d</button>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-auto ms-md-auto">
                                        <button type="button" class="btn btn-sm btn-primary" id="btnFiltrarMovimientos">
                                            <i class="fas fa-search me-1"></i> Filtrar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráfico de Movimientos Diarios -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border border-light-subtle shadow-none">
                                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                            <h6 class="fw-bold mb-0 text-muted"><i class="fas fa-chart-bar me-2"></i>Evolución de Ingresos y Egresos Diarios</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div style="height: 260px;">
                                                <canvas id="chartMovimientos"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Grilla de Movimientos del Período -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark">Libro Subdiario de Tesorería</h6>
                                <small class="text-muted">Mostrando últimos movimientos registrados</small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard align-middle mb-0" id="tablaMovimientos">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Cuenta</th>
                                            <th>Medio de Pago</th>
                                            <th>Concepto</th>
                                            <th>Comprobante</th>
                                            <th class="text-end">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyMovimientos">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando movimientos...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ====================================================== -->
                        <!-- PESTAÑA 3: FLUJO DE FONDOS PROYECTADO                  -->
                        <!-- ====================================================== -->
                        <div class="tab-pane fade" id="tab-flujo" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Proyección Financiera de Cash Flow</h5>
                                    <small class="text-muted">Estimación basada en facturas a cobrar, facturas a pagar y cheques diferidos emitidos y recibidos</small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-horizonte-flujo" data-dias="15">15 Días</button>
                                    <button type="button" class="btn btn-outline-secondary btn-horizonte-flujo active" data-dias="30">30 Días</button>
                                    <button type="button" class="btn btn-outline-secondary btn-horizonte-flujo" data-dias="60">60 Días</button>
                                </div>
                            </div>

                            <!-- Buckets Resumen del Flujo -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="text-muted small fw-bold text-uppercase">Vencidos / Hoy</div>
                                        <div class="d-flex justify-content-between align-items-baseline mt-2">
                                            <span class="text-success small"><i class="fas fa-arrow-down me-1"></i>+<span id="flujoVencidoIng">$0</span></span>
                                            <span class="text-danger small"><i class="fas fa-arrow-up me-1"></i>-<span id="flujoVencidoEgr">$0</span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="text-muted small fw-bold text-uppercase">Días 1 a 7</div>
                                        <div class="d-flex justify-content-between align-items-baseline mt-2">
                                            <span class="text-success small"><i class="fas fa-arrow-down me-1"></i>+<span id="flujo7dIng">$0</span></span>
                                            <span class="text-danger small"><i class="fas fa-arrow-up me-1"></i>-<span id="flujo7dEgr">$0</span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="text-muted small fw-bold text-uppercase">Días 8 a 15</div>
                                        <div class="d-flex justify-content-between align-items-baseline mt-2">
                                            <span class="text-success small"><i class="fas fa-arrow-down me-1"></i>+<span id="flujo15dIng">$0</span></span>
                                            <span class="text-danger small"><i class="fas fa-arrow-up me-1"></i>-<span id="flujo15dEgr">$0</span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="text-muted small fw-bold text-uppercase">Días 16 a 30</div>
                                        <div class="d-flex justify-content-between align-items-baseline mt-2">
                                            <span class="text-success small"><i class="fas fa-arrow-down me-1"></i>+<span id="flujo30dIng">$0</span></span>
                                            <span class="text-danger small"><i class="fas fa-arrow-up me-1"></i>-<span id="flujo30dEgr">$0</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráfico de Saldo Proyectado Acumulado -->
                            <div class="card border border-light-subtle shadow-none mb-4">
                                <div class="card-header bg-white py-2">
                                    <h6 class="fw-bold mb-0 text-muted"><i class="fas fa-chart-area me-2"></i>Evolución del Saldo Acumulado Proyectado</h6>
                                </div>
                                <div class="card-body p-3">
                                    <div style="height: 280px;">
                                        <canvas id="chartFlujo"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla Cronológica del Flujo -->
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-dashboard align-middle mb-0" id="tablaFlujoTimeline">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Día</th>
                                            <th class="text-end text-success">(+) Cobros Esperados</th>
                                            <th class="text-end text-danger">(-) Pagos Esperados</th>
                                            <th class="text-end">(=) Saldo Estimado al Cierre</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyFlujoTimeline">
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Calculando proyección...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ====================================================== -->
                        <!-- PESTAÑA 4: VALORES A DEPOSITAR (CHEQUES)              -->
                        <!-- ====================================================== -->
                        <div class="tab-pane fade" id="tab-valores" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Cartera de Cheques de Terceros</h5>
                                    <small class="text-muted">Cheques físicos y e-cheqs pendientes de depósito o acreditación</small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-chq active" data-cat="">Todos (<span id="cantChqTodos">0</span>)</button>
                                    <button type="button" class="btn btn-outline-danger btn-filtro-chq" data-cat="VENCIDO">Vencidos (<span id="cantChqVencidos">0</span>)</button>
                                    <button type="button" class="btn btn-outline-warning btn-filtro-chq" data-cat="HOY">Al Cobro Hoy (<span id="cantChqHoy">0</span>)</button>
                                    <button type="button" class="btn btn-outline-info btn-filtro-chq" data-cat="PROX_7_DIAS">Próximos 7d (<span id="cantChq7d">0</span>)</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard align-middle mb-0" id="tablaValores">
                                    <thead class="table-light">
                                        <tr>
                                            <th>N° Cheque</th>
                                            <th>Banco</th>
                                            <th>Librador / Entidad</th>
                                            <th>F. Emisión</th>
                                            <th>F. Cobro (Vto.)</th>
                                            <th class="text-center">Días al Cobro</th>
                                            <th class="text-center">Estado / Situación</th>
                                            <th class="text-end">Importe</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyValores">
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando cartera de valores...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

<!-- Modal: Reconciliar Saldo de Cuenta -->
<div class="modal fade" id="modalReconciliarSaldo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-sync-alt me-2"></i>Reconciliar Saldo Materializado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="recCuentaId">
                <p class="mb-3 text-muted">
                    Esta acción recalcula el saldo materializado de la cuenta de tesorería a partir de la suma exacta de ingresos y egresos vigentes en el libro de movimientos y actualiza la fila bajo bloqueo transaccional.
                </p>
                <div class="card bg-light border-0 p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-6"><strong>Cuenta:</strong> <span id="recCuentaNombre">-</span></div>
                        <div class="col-6"><strong>Saldo Materializado:</strong> <span id="recSaldoActual" class="text-primary fw-bold">-</span></div>
                        <div class="col-6"><strong>Saldo según Libro:</strong> <span id="recSaldoLibro" class="text-success fw-bold">-</span></div>
                        <div class="col-6"><strong>Discrepancia:</strong> <span id="recDiferencia" class="text-danger fw-bold">-</span></div>
                    </div>
                </div>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Solo use esta opción para corregir desfasajes provocados por importaciones o ajustes manuales previos.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold" id="btnEjecutarReconciliacion">
                    <i class="fas fa-check me-1"></i> Aplicar Reconciliación
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

<!-- Chart.js y SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="tesoreria_dashboard.js"></script>
