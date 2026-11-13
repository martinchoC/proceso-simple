<?php
/**
 * Vista: Dashboard de stock
 * Módulo 5: Gestión Normal — Página ID 114 (solo lectura, sin tabla propia)
 * Lee gestion__stock (saldo por boca) y gestion__stock_movimientos (libro).
 */
$pageTitle = "Stock - Dashboard";
$currentPage = 'stock_dashboard';
$modudo_idx = 5;
$pagina_idx = 614;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$empresa_id = intval($_SESSION['empresa_id'] ?? 0);
?>

<style>
    .sdb-kpi { border-radius: .75rem; height: 100%; }
    .sdb-kpi .sdb-kpi-icon { width: 44px; height: 44px; border-radius: .6rem; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .sdb-kpi .sdb-kpi-valor { font-size: 1.45rem; font-weight: 700; line-height: 1.2; }
    .sdb-kpi.sdb-clic { cursor: pointer; }
    .sdb-kpi.sdb-clic:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important; }
    .sdb-chart { position: relative; height: 280px; }
    .sdb-top li { display: flex; justify-content: space-between; gap: .5rem; padding: .3rem 0; border-bottom: 1px dashed var(--bs-border-color); }
    .sdb-top li:last-child { border-bottom: 0; }
    .sdb-top .sdb-top-nombre { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sdb-neg { color: var(--bs-danger); font-weight: 700; }
    .sdb-cero { color: var(--bs-secondary-color); }
    #tablaStock tbody tr, #tablaMovimientos tbody tr { cursor: pointer; }
</style>

<main class="app-main">
    <?php ui_encabezado('Stock','bi bi-boxes', 'Stock actual por producto y boca, y el libro de movimientos que lo genera.', ['Stock']); ?>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Filtros generales -->
            <div class="card shadow-sm mb-3">
                <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label for="filtroBoca" class="form-label mb-0 small fw-bold">Boca</label>
                        <select id="filtroBoca" class="form-select form-select-sm" style="min-width: 240px;">
                            <option value="0">Todas las bocas</option>
                        </select>
                    </div>
                    <div>
                        <label for="filtroDesde" class="form-label mb-0 small fw-bold">Movimientos desde</label>
                        <input type="date" id="filtroDesde" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label for="filtroHasta" class="form-label mb-0 small fw-bold">Hasta</label>
                        <input type="date" id="filtroHasta" class="form-control form-control-sm">
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Rango rápido">
                        <button type="button" class="btn btn-outline-secondary btn-rango" data-dias="7">7 días</button>
                        <button type="button" class="btn btn-outline-secondary btn-rango active" data-dias="30">30 días</button>
                        <button type="button" class="btn btn-outline-secondary btn-rango" data-dias="90">90 días</button>
                        <button type="button" class="btn btn-outline-secondary btn-rango" data-dias="365">12 meses</button>
                    </div>
                    <button type="button" id="btnRecargar" class="btn btn-outline-primary btn-sm ms-auto" title="Refrescar">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card sdb-kpi shadow-sm sdb-clic" data-filtro="con_stock">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="sdb-kpi-icon bg-primary-subtle text-primary"><i class="bi bi-box-seam"></i></div>
                            <div><div class="small text-muted">Productos con stock</div><div class="sdb-kpi-valor" id="kpiConStock">—</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card sdb-kpi shadow-sm">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="sdb-kpi-icon bg-info-subtle text-info"><i class="bi bi-stack"></i></div>
                            <div><div class="small text-muted">Unidades en stock</div><div class="sdb-kpi-valor" id="kpiUnidades">—</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card sdb-kpi shadow-sm">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="sdb-kpi-icon bg-success-subtle text-success"><i class="bi bi-currency-dollar"></i></div>
                            <div><div class="small text-muted">Valorizado (costo prom.)</div><div class="sdb-kpi-valor" id="kpiValorizado">—</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card sdb-kpi shadow-sm sdb-clic" data-filtro="bajo_minimo">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="sdb-kpi-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                            <div><div class="small text-muted">Bajo el mínimo</div><div class="sdb-kpi-valor" id="kpiAlertas">—</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card sdb-kpi shadow-sm sdb-clic" data-filtro="negativos">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="sdb-kpi-icon bg-danger-subtle text-danger"><i class="bi bi-dash-circle"></i></div>
                            <div><div class="small text-muted">Stock negativo</div><div class="sdb-kpi-valor" id="kpiNegativos">—</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card sdb-kpi shadow-sm">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="sdb-kpi-icon bg-secondary-subtle text-secondary"><i class="bi bi-arrow-left-right"></i></div>
                            <div>
                                <div class="small text-muted">Movimientos del período</div>
                                <div class="sdb-kpi-valor" id="kpiMovimientos">—</div>
                                <div class="small"><span class="text-success" id="kpiIngresos"></span> · <span class="text-danger" id="kpiEgresos"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico + rankings -->
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-2"><h6 class="mb-0 fw-bold text-muted"><i class="bi bi-bar-chart-fill me-2"></i>Ingresos y egresos de unidades por día</h6></div>
                        <div class="card-body"><div class="sdb-chart"><canvas id="chartMovimientos"></canvas></div></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-2"><h6 class="mb-0 fw-bold text-muted"><i class="bi bi-trophy me-2"></i>Más egresos en el período</h6></div>
                        <div class="card-body py-2">
                            <ol class="list-unstyled sdb-top mb-0 small" id="listaTop"></ol>
                        </div>
                        <div class="card-header py-2 border-top"><h6 class="mb-0 fw-bold text-muted"><i class="bi bi-receipt me-2"></i>Por tipo de comprobante</h6></div>
                        <div class="card-body py-2">
                            <table class="table table-sm mb-0 small">
                                <tbody id="tablaPorTipo"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grillas -->
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header py-2">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tabStockBtn" data-bs-toggle="tab" data-bs-target="#tabStock" type="button" role="tab">
                                <i class="bi bi-box-seam me-1"></i>Stock por producto
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tabMovBtn" data-bs-toggle="tab" data-bs-target="#tabMov" type="button" role="tab">
                                <i class="bi bi-journal-text me-1"></i>Movimientos
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-2">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabStock" role="tabpanel">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <select id="filtroStock" class="form-select form-select-sm" style="max-width: 260px;">
                                    <option value="movidos">Con stock registrado</option>
                                    <option value="con_stock">Con stock positivo</option>
                                    <option value="negativos">Con stock negativo</option>
                                    <option value="bajo_minimo">Bajo el mínimo (alerta abierta)</option>
                                    <option value="todos">Todos los productos activos</option>
                                </select>
                                <select id="filtroCategoria" class="form-select form-select-sm" style="max-width: 260px;">
                                    <option value="0">Todas las categorías</option>
                                </select>
                                <small class="text-muted ms-auto">Clic en una fila para ver su kardex.</small>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaStock" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                                    <thead class="table-light"><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tabMov" role="tabpanel">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <select id="filtroDireccion" class="form-select form-select-sm" style="max-width: 180px;">
                                    <option value="">Ingresos y egresos</option>
                                    <option value="INGRESO">Solo ingresos</option>
                                    <option value="EGRESO">Solo egresos</option>
                                </select>
                                <select id="filtroTipo" class="form-select form-select-sm" style="max-width: 280px;">
                                    <option value="0">Todos los comprobantes</option>
                                </select>
                                <small class="text-muted ms-auto">Período y boca: filtros de arriba.</small>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaMovimientos" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Boca</th>
                                            <th>Código</th>
                                            <th>Producto</th>
                                            <th>Comprobante</th>
                                            <th>Cliente / Proveedor</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Costo unit.</th>
                                            <th>Usuario</th>
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

<!-- Kardex de un producto -->
<div class="modal fade" id="modalKardex" tabindex="-1" aria-labelledby="modalKardexTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalKardexTitulo">Kardex</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="kardexSubtitulo"></div>
                <h6 class="fw-bold">Stock por boca</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sucursal</th><th>Boca</th>
                                <th class="text-end">Stock</th><th class="text-end">Mínimo</th><th class="text-end">Máximo</th>
                                <th class="text-end">Costo prom.</th><th class="text-end">Últ. compra</th><th>Últ. movimiento</th>
                            </tr>
                        </thead>
                        <tbody id="kardexBocas"></tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <h6 class="fw-bold mb-0 me-auto">Movimientos <span class="fw-normal text-muted" id="kardexPeriodo"></span></h6>
                    <select id="kardexBoca" class="form-select form-select-sm" style="max-width: 240px;"></select>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th><th>Boca</th><th>Comprobante</th><th>Cliente / Proveedor</th>
                                <th class="text-end">Ingreso</th><th class="text-end">Egreso</th><th class="text-end">Saldo</th>
                                <th class="text-end">Costo unit.</th><th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody id="kardexMovimientos"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/chart.js-4.4.1/chart.umd.min.js') ?>"></script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
    const SDB_EMPRESA_ID = <?= $empresa_id ?>;
</script>
<script src="stock_dashboard.js?v=<?= filemtime(__DIR__ . '/stock_dashboard.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
