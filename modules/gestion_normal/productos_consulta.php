<?php
// Consulta de productos (Gestión Normal): la misma pantalla que productos.php en SOLO LECTURA.
// Usa productos.js con productos_consulta_ajax.php, que solo deja pasar acciones de lectura.
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Consulta de Productos";
$currentPage = 'productos_consulta';
$modudo_idx = 5;
$pagina_idx = 598; // ID de página en conf__paginas (Consulta de Productos, módulo 5)
$ui_simple = true;
$empresa_idx = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/productos_presentaciones.php';
$usa_presentaciones = prpr_empresa_usa($conn, (int)$empresa_idx);
require_once ROOT_PATH . '/core/productos_marcas.php';
$usa_marcas_comerciales = pmar_empresa_usa($conn, (int)$empresa_idx);
$pmar_solo_lectura = true;
?>

<main class="app-main">
    <?php ui_encabezado('Consulta de productos','fas fa-search', 'Productos y servicios de la empresa, solo para consultar.', ['Seteo Empresas']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="d-inline-block ms-2" style="min-width: 150px;">
                                            <select class="form-select form-select-sm" id="filtroEstado" title="Filtrar por estado">
                                            </select>
                                        </div>
                                        <div class="d-inline-block ms-2" style="min-width: 150px;">
                                            <select class="form-select form-select-sm" id="filtroTipo" title="Filtrar por tipo">
                                                <option value="">Todos los tipos</option>
                                            </select>
                                        </div>
                                        <div class="d-inline-block ms-2" style="min-width: 170px;">
                                            <select class="form-select form-select-sm" id="filtroServicio" title="Productos o servicios">
                                                <option value="">Productos y servicios</option>
                                                <option value="0">Solo productos</option>
                                                <option value="1">Solo servicios</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-3 mb-2 mb-md-0">
                                                <label class="form-label small text-muted mb-1">Código</label>
                                                <input type="text" class="form-select form-select-sm" id="filtroCodigo" placeholder="Filtrar por código..." />
                                            </div>
                                            <div class="col-md-9">
                                                <label class="form-label small text-muted mb-1">Buscar por palabras clave en nombre / código de barras / descripción</label>
                                                <div class="input-group input-group-sm">
                                                    <div class="form-control d-flex flex-wrap align-items-center gap-1 p-1 bg-white" id="buscadorTagsContainer" style="min-height: 38px; cursor: text;">
                                                        <input type="text" id="buscadorTagsInput" class="border-0 flex-grow-1 p-1" placeholder="Escriba palabras clave..." style="outline: none; min-width: 150px;" />
                                                    </div>
                                                    <button class="btn btn-outline-secondary" type="button" id="btnBuscadorLimpiar" title="Limpiar todas las etiquetas">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <table id="tablaProductos" class="table table-bordered table-striped table-hover w-100">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Nombre</th>
                                                    <th>Tipo</th>
                                                    <th>Categoría</th>
                                                    <th>U.M.</th>
                                                    <th>% IVA</th>
                                                    <th>Stock</th>
                                                    <th>Compra</th>
                                                    <th>Venta</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<!-- Modal ABM Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalProductoLabel">
                    <i class="fas fa-cube me-2"></i><span id="modalProductoTitulo">Nuevo Producto / Servicio</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formProducto" class="needs-validation" novalidate>
                    <input type="hidden" id="producto_id" name="producto_id" value="" />
                    <input type="hidden" id="empresa_id" name="empresa_id" value="<?= $empresa_idx ?>" />

                    <!-- Alerta superior cuando es servicio -->
                    <div id="alertaEsServicio" class="alert alert-info py-2 mb-3 d-none">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Modo Servicio:</strong> no controla stock y usa la unidad de medida por defecto.
                    </div>

                    <div class="row g-3">
                        <!-- Sección 1: Datos principales -->
                        <div class="col-12">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-id-card me-1"></i>Identificación Principal
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="producto_codigo" class="form-label required">Código <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="producto_codigo" name="producto_codigo" required maxlength="50" placeholder="Ej: PROD-001" />
                                        <div class="invalid-feedback">Ingrese un código para el producto.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="producto_nombre" class="form-label required">Nombre / Denominación <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="producto_nombre" name="producto_nombre" required maxlength="150" placeholder="Ej: Aceite de Motor 10W40" />
                                        <div class="invalid-feedback">Ingrese el nombre del producto.</div>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="codigo_barras" class="form-label">Código de Barras</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                            <input type="text" class="form-control" id="codigo_barras" name="codigo_barras" maxlength="150" placeholder="7791234567890" />
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="producto_tipo_id" class="form-label required">Tipo de Producto <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="producto_tipo_id" name="producto_tipo_id" required>
                                            <option value="">Seleccionar tipo...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de producto.</div>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="producto_categoria_id" class="form-label required">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="producto_categoria_id" name="producto_categoria_id" required>
                                            <option value="">Seleccionar categoría...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una categoría.</div>
                                    </div>

                                    <?php include ROOT_PATH . '/templates/productos/marca_campo.php'; ?>

                                    <div class="col-md-4">
                                        <label for="unidad_medida_id" class="form-label">Unidad de Medida</label>
                                        <select class="form-select form-select-sm" id="unidad_medida_id" name="unidad_medida_id">
                                            <option value="">(Sin unidad)</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label for="producto_descripcion" class="form-label">Descripción Detallada</label>
                                        <textarea class="form-control form-control-sm" id="producto_descripcion" name="producto_descripcion" rows="2" placeholder="Información técnica, observaciones..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($usa_presentaciones): ?>
                        <!-- Presentaciones (caja, pack, pallet): solo lectura -->
                        <div class="col-12">
                            <?php include ROOT_PATH . '/templates/productos/presentaciones_seccion.php'; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Sección 2: Configuración de Impuestos y Aplicación -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-percentage me-1"></i>Impuestos e Comportamiento
                                </h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="iva_alicuota_id" class="form-label required">Alícuota IVA <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="iva_alicuota_id" name="iva_alicuota_id" required>
                                            <option value="">Seleccionar alícuota...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione la alícuota de IVA.</div>
                                    </div>

                                    <div class="col-6">
                                        <label for="impuesto_interno_porcentaje" class="form-label">Imp. Internos (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" id="impuesto_interno_porcentaje" name="impuesto_interno_porcentaje" placeholder="0.00" />
                                    </div>

                                    <div class="col-6">
                                        <label for="impuesto_interno_fijo" class="form-label">Imp. Interno Fijo ($)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="impuesto_interno_fijo" name="impuesto_interno_fijo" placeholder="0.00" />
                                    </div>

                                    <div class="col-12 mt-3 pt-2 border-top">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="es_servicio" name="es_servicio" value="1" />
                                            <label class="form-check-label fw-bold" for="es_servicio">Es un Servicio (sin control de stock)</label>
                                        </div>

                                        <div class="form-check form-switch mb-2" id="group_controla_stock">
                                            <input class="form-check-input" type="checkbox" id="controla_stock" name="controla_stock" value="1" checked />
                                            <label class="form-check-label" for="controla_stock">Controla Stock</label>
                                        </div>

                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="aplica_compra" name="aplica_compra" value="1" checked />
                                            <label class="form-check-label" for="aplica_compra">Aplica a Compras</label>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="aplica_venta" name="aplica_venta" value="1" checked />
                                            <label class="form-check-label" for="aplica_venta">Aplica a Ventas</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 3: Atributos Físicos -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-ruler-combined me-1"></i>Atributos Físicos
                                </h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label for="lado" class="form-label">Lado</label>
                                        <select class="form-select form-select-sm" id="lado" name="lado">
                                            <option value="">(No aplica)</option>
                                            <option value="IZQUIERDO">Izquierdo</option>
                                            <option value="DERECHO">Derecho</option>
                                            <option value="AMBOS">Ambos / Indistinto</option>
                                            <option value="DELANTERO">Delantero</option>
                                            <option value="TRASERO">Trasero</option>
                                        </select>
                                    </div>

                                    <div class="col-6">
                                        <label for="color" class="form-label">Color</label>
                                        <input type="text" class="form-control form-control-sm" id="color" name="color" maxlength="50" placeholder="Ej: Negro / Cromo" />
                                    </div>

                                    <div class="col-6">
                                        <label for="material" class="form-label">Material</label>
                                        <input type="text" class="form-control form-control-sm" id="material" name="material" maxlength="50" placeholder="Ej: Plástico / Aluminio" />
                                    </div>

                                    <div class="col-6">
                                        <label for="peso" class="form-label">Peso (kg)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="peso" name="peso" placeholder="0.00" />
                                    </div>

                                    <div class="col-6">
                                        <label for="dimensiones" class="form-label">Dimensiones</label>
                                        <input type="text" class="form-control form-control-sm" id="dimensiones" name="dimensiones" maxlength="50" placeholder="Alto x Ancho x Prof (cm)" />
                                    </div>

                                    <div class="col-6">
                                        <label for="garantia" class="form-label">Garantía</label>
                                        <input type="text" class="form-control form-control-sm" id="garantia" name="garantia" maxlength="50" placeholder="Ej: 6 meses" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 4: Cuentas Contables Específicas -->
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-calculator me-1"></i>Imputaciones Contables (Opcional - sobrescribe las del tipo)
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="cont_cuenta_compra_id" class="form-label">Cuenta de Compra / Mercaderías</label>
                                        <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_compra_id" name="cont_cuenta_compra_id">
                                            <option value="">(Usar la cuenta del tipo)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cont_cuenta_venta_id" class="form-label">Cuenta de Venta / Ingresos</label>
                                        <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_venta_id" name="cont_cuenta_venta_id">
                                            <option value="">(Usar la cuenta del tipo)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cont_cuenta_cmv_id" class="form-label">Cuenta de Costo (CMV)</label>
                                        <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_cmv_id" name="cont_cuenta_cmv_id">
                                            <option value="">(Usar la cuenta del tipo)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 5: Stock mínimo / máximo por boca (gestion__stock_niveles). Se graba con el producto; solo si controla stock. -->
                        <div class="col-12" id="seccionNivelesStock">
                            <div class="niveles-stock">
                                <div class="niveles-stock-header">
                                    <span class="niveles-stock-icono"><i class="bi bi-layers-fill"></i></span>
                                    <div>
                                        <div class="fw-semibold">Stock mínimo y máximo por boca</div>
                                        <div class="small text-muted">Si el stock de una boca queda por debajo del mínimo, se genera una alerta de reposición.</div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0" id="tablaNivelesStock">
                                        <thead>
                                            <tr>
                                                <th>Boca</th>
                                                <th class="text-center" style="width: 22%;">Stock actual</th>
                                                <th style="width: 21%;">Mínimo</th>
                                                <th style="width: 21%;">Máximo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="4" class="text-center text-muted small py-3"><i class="fas fa-spinner fa-spin me-1"></i>Cargando bocas...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="niveles-stock-leyenda">
                                    <span><i class="bi bi-circle-fill text-success"></i>En nivel</span>
                                    <span><i class="bi bi-circle-fill text-danger"></i>Bajo el mínimo</span>
                                    <span><i class="bi bi-circle-fill text-secondary"></i>Sin control (mínimo vacío)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarProducto">
                    <i class="fas fa-save me-1"></i>Guardar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<style>
        /* Stock mínimo / máximo por boca (modal de producto) */
        .niveles-stock {
            border: 1px solid var(--bs-border-color);
            border-radius: .5rem;
            overflow: hidden;
            background: var(--bs-body-bg);
        }
        .niveles-stock-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem .85rem;
            background: var(--bs-tertiary-bg);
            border-bottom: 1px solid var(--bs-border-color);
        }
        .niveles-stock-icono {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary);
            flex-shrink: 0;
        }
        #tablaNivelesStock thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--bs-secondary-color);
            font-weight: 600;
            border-bottom-width: 1px;
            padding: .45rem .85rem;
            background: transparent;
        }
        #tablaNivelesStock tbody td {
            padding: .4rem .85rem;
            border-color: var(--bs-border-color-translucent);
        }
        #tablaNivelesStock tbody tr:last-child td {
            border-bottom: 0;
        }
        #tablaNivelesStock tbody tr.nivel-bajo td {
            background: var(--bs-danger-bg-subtle);
        }
        #tablaNivelesStock .nivel-boca {
            font-weight: 600;
            line-height: 1.2;
        }
        #tablaNivelesStock .nivel-sucursal {
            font-size: .75rem;
            color: var(--bs-secondary-color);
        }
        #tablaNivelesStock .nivel-stock {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            min-width: 4.5rem;
            justify-content: center;
            padding: .25rem .6rem;
            border-radius: 50rem;
            font-weight: 600;
            font-size: .8rem;
            font-variant-numeric: tabular-nums;
        }
        #tablaNivelesStock .nivel-stock.ok {
            background: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }
        #tablaNivelesStock .nivel-stock.bajo {
            background: var(--bs-danger);
            color: #fff;
        }
        #tablaNivelesStock .nivel-stock.sin-control {
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
        }
        #tablaNivelesStock .input-group-text {
            font-size: .7rem;
            color: var(--bs-secondary-color);
            min-width: 2.6rem;
            justify-content: center;
        }
        #tablaNivelesStock input.form-control {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        #tablaNivelesStock input.form-control::placeholder {
            color: var(--bs-tertiary-color);
            font-style: italic;
        }
        .niveles-stock-leyenda {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: .45rem .85rem;
            font-size: .72rem;
            color: var(--bs-secondary-color);
            border-top: 1px solid var(--bs-border-color);
            background: var(--bs-tertiary-bg);
        }
        .niveles-stock-leyenda i {
            font-size: .5rem;
            margin-right: .35rem;
            vertical-align: middle;
        }
</style>

<script>
    const EMPRESA_ID = <?= json_encode($empresa_idx) ?>;
    const PAGINA_ID = <?= json_encode($pagina_idx) ?>;
    window.PRODUCTOS_AJAX_URL = 'productos_consulta_ajax.php';
    window.PRODUCTOS_SOLO_LECTURA = true;
</script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<?php if ($usa_presentaciones): ?>
<script src="<?= asset_local('js/productos-presentaciones.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/productos-presentaciones.js') ?>"></script>
<?php endif; ?>
<?php if ($usa_marcas_comerciales): ?>
<script src="<?= asset_local('js/productos-marcas.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/productos-marcas.js') ?>"></script>
<?php endif; ?>
<script src="productos.js?v=<?= filemtime(__DIR__ . '/productos.js') ?>"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>