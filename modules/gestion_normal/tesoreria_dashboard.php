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
$modudo_idx = 5;
$pagina_idx = 604;
$ui_simple = true;

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

// Selector de sucursal y recarga, a la derecha del título
ob_start();
?>
<select class="form-select form-select-sm" id="filtroSucursal" style="width: 200px;" aria-label="Sucursal">
    <option value="">Todas las sucursales</option>
    <?php foreach ($sucursales as $suc): ?>
        <option value="<?php echo $suc['sucursal_id']; ?>"><?php echo htmlspecialchars($suc['sucursal_nombre']); ?></option>
    <?php endforeach; ?>
</select>
<button type="button" class="btn btn-sm btn-light" id="btnActualizarDashboard" title="Actualizar">
    <i class="bi bi-arrow-clockwise"></i>
</button>
<?php
$acciones_encabezado = ob_get_clean();
?>

<style>
/* Usadas por tesoreria_dashboard.js en los estados de cuentas, movimientos y cheques */
.badge-subtle-success { background-color: #d1e7dd; color: #0f5132; }
.badge-subtle-danger { background-color: #f8d7da; color: #842029; }
.badge-subtle-warning { background-color: #fff3cd; color: #664d03; }
.badge-subtle-primary { background-color: #cfe2ff; color: #084298; }
.badge-subtle-info { background-color: #cff4fc; color: #055160; }
</style>

<?php ui_encabezado('Dashboard financiero', 'bi bi-bank',
    'Saldos de cajas y bancos, movimientos del período, proyección de cobros y pagos, y cheques en cartera.',
    ['Tesorería'], $acciones_encabezado); ?>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Indicadores -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Disponible</div>
                        <div class="ui-kpi-valor" id="kpiSaldoConsolidado">$ 0,00</div>
                        <div class="ui-kpi-sub">
                            Cajas <span id="kpiSaldoCajas">$ 0,00</span> · Bancos <span id="kpiSaldoBancos">$ 0,00</span>
                        </div>
                        <div class="ui-kpi-sub">
                            En dólares <span id="kpiSaldoUSD">U$S 0,00</span>
                            (a <span id="kpiCotizacionUSD">$ 0,00</span>) · Total <span id="kpiConsolidadoTotalARS">$ 0,00</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Flujo del período</div>
                        <div class="ui-kpi-valor" id="kpiFlujoNeto">$ 0,00</div>
                        <div class="ui-kpi-sub">
                            Entró <span id="kpiTotalIngresos">$ 0,00</span> · Salió <span id="kpiTotalEgresos">$ 0,00</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Cheques en cartera</div>
                        <div class="ui-kpi-valor" id="kpiCarteraMonto">$ 0,00</div>
                        <div class="ui-kpi-sub d-flex justify-content-between align-items-center">
                            <span id="kpiCarteraCant">0 cheques</span>
                            <span id="kpiCarteraAlertas" class="badge badge-subtle-success">Al día</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aviso de diferencia contable (lo muestra la auditoría de saldos) -->
            <div id="alertaDesfasajeContable" class="alert alert-warning ui-aviso d-none mb-3" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <div>
                    <strong class="d-block">Hay cuentas con diferencias</strong>
                    <span id="alertaDesfasajeTexto">Se detectó una discrepancia entre el saldo materializado y los asientos del libro de tesorería en una o más cuentas.</span>
                </div>
            </div>

            <div class="card">
                <div class="card-header pb-0 border-0">
                    <ul class="nav ui-solapas" id="dashboardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-cuentas-btn" data-bs-toggle="pill" data-bs-target="#tab-cuentas" type="button" role="tab">Cuentas</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-movimientos-btn" data-bs-toggle="pill" data-bs-target="#tab-movimientos" type="button" role="tab">Movimientos</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-flujo-btn" data-bs-toggle="pill" data-bs-target="#tab-flujo" type="button" role="tab">Proyección</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-valores-btn" data-bs-toggle="pill" data-bs-target="#tab-valores" type="button" role="tab">Cheques</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="dashboardTabsContent">

                        <!-- Cuentas y saldos -->
                        <div class="tab-pane fade show active" id="tab-cuentas" role="tabpanel">
                            <div class="d-flex justify-content-end mb-3">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Tipo de cuenta">
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta active" data-tipo="">Todas</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta" data-tipo="CAJA">Cajas</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta" data-tipo="BANCO">Bancos</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-cta" data-tipo="VALORES">Valores</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard align-middle mb-0" id="tablaCuentas">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Cuenta</th>
                                            <th>Cuenta contable</th>
                                            <th>Banco / CBU</th>
                                            <th>Sucursal</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-center">Control <?= ui_ayuda('Compara el saldo de la cuenta con la suma de sus movimientos.') ?></th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCuentas">
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <span class="spinner-border spinner-border-sm me-2"></span>Cargando…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Movimientos del período -->
                        <div class="tab-pane fade" id="tab-movimientos" role="tabpanel">
                            <div class="ui-filtros mb-3">
                                <div class="ui-f-fecha">
                                    <label class="form-label" for="movFechaDesde">Desde</label>
                                    <input type="date" class="form-control form-control-sm" id="movFechaDesde" value="<?php echo date('Y-m-01'); ?>">
                                </div>
                                <div class="ui-f-fecha">
                                    <label class="form-label" for="movFechaHasta">Hasta</label>
                                    <input type="date" class="form-control form-control-sm" id="movFechaHasta" value="<?php echo date('Y-m-t'); ?>">
                                </div>
                                <div class="ui-f-ancha">
                                    <label class="form-label" for="movCuentaId">Cuenta</label>
                                    <select class="form-select form-select-sm" id="movCuentaId">
                                        <option value="">Todas las cuentas</option>
                                    </select>
                                </div>
                                <div style="min-width: 0;">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Período">
                                        <button type="button" class="btn btn-outline-secondary btn-preset-fecha" data-preset="hoy">Hoy</button>
                                        <button type="button" class="btn btn-outline-secondary btn-preset-fecha" data-preset="semana">Semana</button>
                                        <button type="button" class="btn btn-outline-secondary btn-preset-fecha active" data-preset="mes">Mes</button>
                                        <button type="button" class="btn btn-outline-secondary btn-preset-fecha" data-preset="30d">30 días</button>
                                    </div>
                                </div>
                                <div class="ms-auto" style="min-width: 0;">
                                    <button type="button" class="btn btn-sm btn-primary" id="btnFiltrarMovimientos">Ver</button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="ui-seccion-titulo mb-2">Entradas y salidas por día</h6>
                                <div style="height: 240px;">
                                    <canvas id="chartMovimientos"></canvas>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard align-middle mb-0" id="tablaMovimientos">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Cuenta</th>
                                            <th>Medio</th>
                                            <th>Concepto</th>
                                            <th>Comprobante</th>
                                            <th class="text-end">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyMovimientos">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <span class="spinner-border spinner-border-sm me-2"></span>Cargando…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Proyección de cobros y pagos -->
                        <div class="tab-pane fade" id="tab-flujo" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                                <span class="text-muted small">Facturas a cobrar y a pagar, y cheques diferidos
                                    <?= ui_ayuda('Incluye cheques recibidos y emitidos con fecha de pago futura.') ?></span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Horizonte">
                                    <button type="button" class="btn btn-outline-secondary btn-horizonte-flujo" data-dias="15">15 días</button>
                                    <button type="button" class="btn btn-outline-secondary btn-horizonte-flujo active" data-dias="30">30 días</button>
                                    <button type="button" class="btn btn-outline-secondary btn-horizonte-flujo" data-dias="60">60 días</button>
                                </div>
                            </div>

                            <div class="row g-2 mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="ui-kpi ui-kpi-chico">
                                        <div class="ui-kpi-etiqueta">Vencido / hoy</div>
                                        <div class="small"><span class="text-success">+<span id="flujoVencidoIng">$0</span></span> · <span class="text-danger">-<span id="flujoVencidoEgr">$0</span></span></div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="ui-kpi ui-kpi-chico">
                                        <div class="ui-kpi-etiqueta">Días 1 a 7</div>
                                        <div class="small"><span class="text-success">+<span id="flujo7dIng">$0</span></span> · <span class="text-danger">-<span id="flujo7dEgr">$0</span></span></div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="ui-kpi ui-kpi-chico">
                                        <div class="ui-kpi-etiqueta">Días 8 a 15</div>
                                        <div class="small"><span class="text-success">+<span id="flujo15dIng">$0</span></span> · <span class="text-danger">-<span id="flujo15dEgr">$0</span></span></div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="ui-kpi ui-kpi-chico">
                                        <div class="ui-kpi-etiqueta">Días 16 a 30</div>
                                        <div class="small"><span class="text-success">+<span id="flujo30dIng">$0</span></span> · <span class="text-danger">-<span id="flujo30dEgr">$0</span></span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="ui-seccion-titulo mb-2">Saldo proyectado</h6>
                                <div style="height: 260px;">
                                    <canvas id="chartFlujo"></canvas>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-dashboard align-middle mb-0" id="tablaFlujoTimeline">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Día</th>
                                            <th class="text-end">Cobros</th>
                                            <th class="text-end">Pagos</th>
                                            <th class="text-end">Saldo estimado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyFlujoTimeline">
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <span class="spinner-border spinner-border-sm me-2"></span>Calculando…
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Cheques de terceros en cartera -->
                        <div class="tab-pane fade" id="tab-valores" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                                <span class="text-muted small">Cheques y e-cheqs sin depositar</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Situación">
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-chq active" data-cat="">Todos (<span id="cantChqTodos">0</span>)</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-chq" data-cat="VENCIDO">Vencidos (<span id="cantChqVencidos">0</span>)</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-chq" data-cat="HOY">Hoy (<span id="cantChqHoy">0</span>)</button>
                                    <button type="button" class="btn btn-outline-secondary btn-filtro-chq" data-cat="PROX_7_DIAS">7 días (<span id="cantChq7d">0</span>)</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-dashboard align-middle mb-0" id="tablaValores">
                                    <thead>
                                        <tr>
                                            <th>N° cheque</th>
                                            <th>Banco</th>
                                            <th>Librador</th>
                                            <th>Emisión</th>
                                            <th>Cobro</th>
                                            <th class="text-center">Días</th>
                                            <th>Situación</th>
                                            <th class="text-end">Importe</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyValores">
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <span class="spinner-border spinner-border-sm me-2"></span>Cargando…
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
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-arrow-repeat me-2"></i>Corregir saldo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="recCuentaId">
                <p class="mb-3">Recalcula el saldo de la cuenta sumando sus movimientos.</p>
                <dl class="row mb-3 small">
                    <dt class="col-6 fw-normal text-muted">Cuenta</dt>
                    <dd class="col-6 mb-1" id="recCuentaNombre">-</dd>
                    <dt class="col-6 fw-normal text-muted">Saldo actual</dt>
                    <dd class="col-6 mb-1 fw-semibold" id="recSaldoActual">-</dd>
                    <dt class="col-6 fw-normal text-muted">Según movimientos</dt>
                    <dd class="col-6 mb-1 fw-semibold" id="recSaldoLibro">-</dd>
                    <dt class="col-6 fw-normal text-muted">Diferencia</dt>
                    <dd class="col-6 mb-0 fw-semibold text-danger" id="recDiferencia">-</dd>
                </dl>
                <div class="alert alert-light border ui-aviso mb-0">
                    <i class="bi bi-info-circle"></i>
                    <span>Usalo solo para corregir diferencias de importaciones o ajustes manuales.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnEjecutarReconciliacion">Corregir saldo</button>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

<!-- Chart.js y SweetAlert2 -->
<script src="<?= asset_local('js/chart.js-4.5.1/chart.umd.min.js') ?>"></script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="tesoreria_dashboard.js"></script>
