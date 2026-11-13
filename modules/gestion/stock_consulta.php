<?php
/**
 * Vista: Stock y Movimientos
 * Módulo 2: Gestión de Empresas — Página ID 138 (sin tabla propia)
 * Stock por producto (una columna por boca) y libro de movimientos, con filtros de sucursal, boca, producto,
 * período, situación, categoría, compatibilidad (marca / modelo / submodelo, solo este módulo), dirección y
 * comprobante. Las consultas son las del Dashboard de stock (stock_dashboard_model.php).
 * Además: planilla de conteo de lo filtrado y blanqueo de stock negativo (genera Ajustes de Stock).
 * Script: _local/sql/mantenimiento/20261103_stock_consulta_01_pagina.sql
 */
$pageTitle = "Stock y Movimientos";
$currentPage = 'stock_consulta';
$modudo_idx = 2;
$pagina_idx = 138;
$ui_simple = true;
$sc_compatibilidad = true;   // filtros de marca / modelo / submodelo (Gestión Normal no tiene compatibilidad)

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$empresa_id = intval($current_empresa_id ?? 0);
?>
<link rel="stylesheet" href="<?= asset_local('css/select2-4.1.0-rc.0/select2.min.css') ?>">
<link rel="stylesheet" href="<?= asset_local('css/select2-bootstrap-5-theme-1.3.0/select2-bootstrap-5-theme.min.css') ?>">
<style>
    .sc-neg { color: var(--bs-danger); font-weight: 700; }
    .sc-cero { color: var(--bs-secondary-color); }
    #tablaStock tbody tr { cursor: pointer; }
    #scFiltros .select2-container { min-width: 260px; }
</style>

<main class="app-main">
    <?php ui_encabezado('Stock y Movimientos', 'bi bi-table',
        'Stock de cada producto por boca y los movimientos que lo generan. Tocá un producto para ver sus movimientos.', ['Stock']); ?>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Filtros generales (valen para las dos solapas) -->
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="ui-filtros d-flex flex-wrap gap-2 align-items-end" id="scFiltros">
                        <div>
                            <label for="filtroSucursal" class="form-label mb-0 small">Sucursal</label>
                            <select id="filtroSucursal" class="form-select form-select-sm ui-f-media"><option value="0">Todas</option></select>
                        </div>
                        <div>
                            <label for="filtroBoca" class="form-label mb-0 small">Boca</label>
                            <select id="filtroBoca" class="form-select form-select-sm ui-f-media"><option value="0">Todas</option></select>
                        </div>
                        <div>
                            <label for="filtroProducto" class="form-label mb-0 small">Producto</label>
                            <select id="filtroProducto" class="form-select form-select-sm ui-f-ancha"></select>
                        </div>
                        <div>
                            <label for="filtroCategoria" class="form-label mb-0 small">Categoría</label>
                            <select id="filtroCategoria" class="form-select form-select-sm ui-f-media"><option value="0">Todas</option></select>
                        </div>
<?php if ($sc_compatibilidad): ?>
                        <div>
                            <label for="filtroMarca" class="form-label mb-0 small">Marca</label>
                            <select id="filtroMarca" class="form-select form-select-sm ui-f-media"><option value="0">Todas</option></select>
                        </div>
                        <div>
                            <label for="filtroModelo" class="form-label mb-0 small">Modelo</label>
                            <select id="filtroModelo" class="form-select form-select-sm ui-f-media" disabled><option value="0">Todos</option></select>
                        </div>
                        <div>
                            <label for="filtroSubmodelo" class="form-label mb-0 small">Submodelo</label>
                            <select id="filtroSubmodelo" class="form-select form-select-sm ui-f-media" disabled><option value="0">Todos</option></select>
                        </div>
<?php endif; ?>
                        <div>
                            <label for="filtroDesde" class="form-label mb-0 small">Movimientos desde <?= ui_ayuda('El período filtra los movimientos; el stock es siempre el actual.') ?></label>
                            <input type="date" id="filtroDesde" class="form-control form-control-sm ui-f-fecha">
                        </div>
                        <div>
                            <label for="filtroHasta" class="form-label mb-0 small">Hasta</label>
                            <input type="date" id="filtroHasta" class="form-control form-control-sm ui-f-fecha">
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Rango rápido">
                            <button type="button" class="btn btn-outline-secondary btn-rango" data-dias="7">7 días</button>
                            <button type="button" class="btn btn-outline-secondary btn-rango active" data-dias="30">30 días</button>
                            <button type="button" class="btn btn-outline-secondary btn-rango" data-dias="90">90 días</button>
                            <button type="button" class="btn btn-outline-secondary btn-rango" data-dias="365">12 meses</button>
                        </div>
                        <button type="button" id="btnLimpiar" class="btn btn-sm btn-outline-secondary" title="Quitar filtros"><i class="bi bi-x-lg"></i> Limpiar</button>
                        <button type="button" id="btnRecargar" class="btn btn-sm btn-outline-primary" title="Actualizar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
            </div>

            <!-- Totales de lo filtrado -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Productos</div>
                        <div class="ui-kpi-valor" id="kpiProductos">-</div>
                        <div class="ui-kpi-sub" id="kpiNegativos"></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Unidades en stock</div>
                        <div class="ui-kpi-valor" id="kpiUnidades">-</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Valorizado <?= ui_ayuda('Stock positivo por el costo promedio de cada boca.') ?></div>
                        <div class="ui-kpi-valor" id="kpiValorizado">-</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Movimientos del período</div>
                        <div class="ui-kpi-valor" id="kpiMovimientos">-</div>
                        <div class="ui-kpi-sub"><span class="text-success" id="kpiIngresos"></span> · <span class="text-danger" id="kpiEgresos"></span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2">
                    <ul class="nav nav-tabs card-header-tabs ui-solapas" role="tablist">
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
                                <select id="filtroStock" class="form-select form-select-sm" style="max-width: 260px;" aria-label="Situación del stock">
                                    <option value="movidos">Con stock registrado</option>
                                    <option value="con_stock">Con stock positivo</option>
                                    <option value="negativos">Con stock negativo</option>
                                    <option value="bajo_minimo">Bajo el mínimo (alerta abierta)</option>
                                    <option value="todos">Todos los productos activos</option>
                                </select>
                                <div class="input-group input-group-sm" style="max-width: 320px;">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="search" id="filtroBuscar" class="form-control" placeholder="Buscar código, nombre o cód. de barras" aria-label="Buscar producto">
                                </div>
                                <div class="ms-auto d-flex flex-wrap gap-2">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-clipboard-check me-1"></i>Planilla de conteo
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item btn-planilla" href="#" data-ciega="1">Sin stock del sistema (conteo a ciegas)</a></li>
                                            <li><a class="dropdown-item btn-planilla" href="#" data-ciega="0">Con stock del sistema</a></li>
                                        </ul>
                                    </div>
                                    <button type="button" id="btnBlanqueo" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-eraser me-1"></i>Blanquear negativos
                                    </button>
                                    <?= ui_ayuda('Planilla de conteo: los productos de la grilla con los filtros aplicados, agrupados por boca, para imprimir y anotar lo contado. Blanquear negativos: genera un Ajuste de Stock por boca que lleva a 0 cada producto en negativo dentro de los filtros (sucursal, boca, producto, categoría, búsqueda' . ($sc_compatibilidad ? ' y compatibilidad' : '') . ').') ?>
                                </div>
                            </div>
                            <table id="tablaStock" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                                <thead class="table-light"><tr></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="tab-pane fade" id="tabMov" role="tabpanel">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <select id="filtroDireccion" class="form-select form-select-sm" style="max-width: 180px;" aria-label="Dirección">
                                    <option value="">Ingresos y egresos</option>
                                    <option value="INGRESO">Solo ingresos</option>
                                    <option value="EGRESO">Solo egresos</option>
                                </select>
                                <select id="filtroTipo" class="form-select form-select-sm" style="max-width: 280px;" aria-label="Comprobante">
                                    <option value="0">Todos los comprobantes</option>
                                </select>
                            </div>
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
                                        <th class="ui-detalle">Usuario</th>
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
</main>

<script src="<?= asset_local('js/select2-4.1.0-rc.0/select2.min.js') ?>"></script>
<script src="<?= asset_local('js/select2-4.1.0-rc.0/i18n/es.js') ?>"></script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
    const SC_EMPRESA_ID = <?= $empresa_id ?>;
    const SC_EMPRESA_NOMBRE = <?= json_encode((string)($nombre_empresa_actual ?? ''), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="stock_consulta.js?v=<?= filemtime(__DIR__ . '/stock_consulta.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
