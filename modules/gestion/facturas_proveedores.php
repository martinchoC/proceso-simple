<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Facturas de Proveedores";
$currentPage = 'facturas_proveedores';
$modudo_idx = 2;
$pagina_idx = 57; // Nuevo ID para facturas proveedor

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice me-2"></i>Facturas de Proveedores
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Compras</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Facturas de Proveedores</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

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
                                        <div class="float-end">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    id="btnRecargar" title="Recargar tabla">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Exportar datos">
                                                    <i class="fas fa-file-export"></i> Exportar
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i
                                                                class="fas fa-file-excel text-success"></i> Excel</a>
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i
                                                                class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i
                                                                class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i
                                                                class="fas fa-print text-secondary"></i> Imprimir</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <table id="tablaFacturasProveedor" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="100">Tipo</th>
                                                    <th width="120">Número</th>
                                                    <th width="200">Proveedor</th>
                                                    <th width="120">Sucursal</th>
                                                    <th width="120">Depósito</th>
                                                    <th width="100">Emisión</th>
                                                    <th width="120">Vencimiento</th>
                                                    <th width="120">Total</th>
                                                    <th width="120">Estado</th>
                                                    <th width="250" class="text-center">Acciones</th>
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

            <!-- Modal principal -->
            <div class="modal fade" id="modalFacturaProveedor" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width: 1600px; width: 95%;">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-file-invoice me-2 text-primary"></i>Factura de Proveedor
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formFacturaProveedor" class="needs-validation" novalidate>
                                <input type="hidden" id="factura_proveedor_id" name="factura_proveedor_id" />
                                <input type="hidden" id="producto_iva_id" name="producto_iva_id" />
                                <input type="hidden" id="entidad_id" name="entidad_id" />
                                <input type="hidden" id="entidad_sucursal_id" name="entidad_sucursal_id" />
                                <input type="hidden" id="otros_impuestos" name="otros_impuestos" value="0">
                                
                                <!-- PESTAÑAS -->
                                <ul class="nav nav-tabs mb-3" id="facturaTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">
                                            <i class="fas fa-file-invoice me-1"></i>Datos
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="productos-tab" data-bs-toggle="tab" data-bs-target="#productos" type="button" role="tab">
                                            <i class="fas fa-boxes me-1"></i>Productos
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="impuestos-tab" data-bs-toggle="tab" data-bs-target="#impuestos" type="button" role="tab">
                                            <i class="fas fa-calculator me-1"></i>Otros Impuestos
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <!-- TAB 1: DATOS DE LA FACTURA -->
                                    <div class="tab-pane fade show active" id="datos" role="tabpanel">
                                        <div class="card mb-3 border-primary">
                                            <div class="card-header py-2 bg-primary bg-opacity-10">
                                                <h6 class="mb-0 text-primary"><i class="fas fa-file-invoice me-2"></i>Datos de la Factura</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <!-- Fila 1: Sucursal, Depósito, Proveedor -->
                                                <div class="row mb-2">
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Sucursal</label>
                                                        <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                                            <option value="">Seleccionar</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Depósito</label>
                                                        <select class="form-select form-select-sm" id="deposito_id" name="deposito_id" required>
                                                            <option value="">Seleccionar</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small mb-1">Proveedor / Sucursal</label>
                                                        <select class="form-select form-select-sm" id="entidad_combo" required>
                                                            <option value="">Seleccionar</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label small mb-1">Tipo</label>
                                                        <select class="form-select form-select-sm" id="comprobante_tipo_id" required>
                                                            <option value="">Sel.</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label small mb-1">PV</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" id="comprobante_pv" value="0" min="0" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Número</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" id="comprobante_nro" value="0" min="1" required>
                                                    </div>
                                                </div>

                                                <!-- Fila 2: Fechas y otros campos -->
                                                <div class="row mb-2">
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Emisión</label>
                                                        <input type="date" class="form-control form-control-sm" id="f_emision" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Contabilidad</label>
                                                        <input type="date" class="form-control form-control-sm" id="f_contabilidad">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Vencimiento</label>
                                                        <input type="date" class="form-control form-control-sm" id="f_vencimiento">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Moneda</label>
                                                        <select class="form-select form-select-sm" id="moneda_id" required>
                                                            <option value="">Sel.</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label small mb-1">TC</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" id="tipo_cambio" step="0.000001" value="1">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Cond. Pago</label>
                                                        <select class="form-select form-select-sm" id="condicion_pago_id">
                                                            <option value="">Seleccionar</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label small mb-1">Dto.%</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" id="descuento_general_pct" step="0.01" min="0" max="100" value="0">
                                                    </div>
                                                </div>

                                                <!-- Fila 3: Dirección y Observaciones (solo placeholders) -->
                                                <div class="row mb-2">
                                                    <div class="col-md-6">
                                                        <textarea class="form-control form-control-sm" id="direccion" name="direccion" rows="1" placeholder="Dirección..."></textarea>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <textarea class="form-control form-control-sm" id="observaciones" name="observaciones" rows="1" placeholder="Observaciones..."></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Totales -->
                                        <div class="card bg-success bg-opacity-10 border-success mb-3">
                                            <div class="card-body py-2">
                                                <div class="row align-items-center small">
                                                    <div class="col-md-2"><span class="fw-bold">Neto:</span> <span id="total_neto_display">0.00</span></div>
                                                    <div class="col-md-2">Descuentos: <span id="descuentos_display">0.00</span></div>
                                                    <div class="col-md-2">No Gravado: <span id="no_gravado_display">0.00</span></div>
                                                    <div class="col-md-2">Exento: <span id="exento_display">0.00</span></div>
                                                    <div class="col-md-2">IVA: <span id="impuestos_display">0.00</span></div>
                                                    <div class="col-md-2">Otros Imp: <span id="otros_impuestos_display">0.00</span></div>
                                                    <div class="col-md-2 fw-bold text-primary">TOTAL: <span id="total_display">0.00</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB 2: PRODUCTOS -->
                                    <div class="tab-pane fade" id="productos" role="tabpanel">
                                        <div class="card border-warning">
                                            <div class="card-header py-2 bg-warning bg-opacity-10 d-flex justify-content-between">
                                                <h6 class="mb-0 text-warning"><i class="fas fa-boxes me-2"></i>Detalle de Productos</h6>
                                                <button type="button" class="btn btn-sm btn-outline-warning" id="btnNuevoProductoRapido">
                                                    <i class="fas fa-bolt me-1"></i>Nuevo Producto Rápido
                                                </button>
                                            </div>
                                            <div class="card-body p-2">
                                                <!-- Agregar producto rápido -->
                                                <div class="card card-info card-outline mb-3">
                                                    <div class="card-header py-1 bg-info bg-opacity-10">
                                                        <h6 class="mb-0 small"><i class="fas fa-plus-circle me-2"></i>Agregar Producto</h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="row g-1 align-items-end">
                                                            <div class="col-md-4 position-relative">
                                                                <input type="text" class="form-control form-control-sm" id="busqueda_producto" placeholder="Buscar producto..." autocomplete="off">
                                                                <input type="hidden" id="producto_seleccionado_id">
                                                                <div id="resultados_busqueda" class="list-group position-absolute" style="z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%; display: none;"></div>
                                                            </div>
                                                            <div class="col-md-1">
                                                                <input type="number" class="form-control form-control-sm no-spinner" id="producto_cantidad" step="0.01" min="0.01" value="1.00" placeholder="Cant.">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <input type="number" class="form-control form-control-sm no-spinner" id="producto_precio" step="0.01" min="0" placeholder="Precio">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <input type="number" class="form-control form-control-sm no-spinner" id="producto_descuento_item_pct" step="0.01" min="0" max="100" value="0" placeholder="Dto%">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <select class="form-select form-select-sm" id="producto_iva">
                                                                    <option value="21">21%</option><option value="10.5">10.5%</option>
                                                                    <option value="27">27%</option><option value="0">0%</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-1">
                                                                <input type="number" class="form-control form-control-sm no-spinner" id="producto_no_gravado" step="0.01" value="0" placeholder="No Grav.">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <input type="number" class="form-control form-control-sm no-spinner" id="producto_exento" step="0.01" value="0" placeholder="Exento">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <button type="button" class="btn btn-sm btn-success w-100" id="btnAgregarProducto">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Tabla de detalles -->
                                                <div id="contenedor-detalles" class="table-responsive">
                                                    <div class="detalles-vacio text-center p-4 border rounded bg-light">
                                                        <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                                                        <p class="mb-0">No hay productos agregados</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- TAB 3: OTROS IMPUESTOS -->
                                    <div class="tab-pane fade" id="impuestos" role="tabpanel">
                                        <div class="card border-info">
                                            <div class="card-header py-2 bg-info bg-opacity-10 d-flex justify-content-between">
                                                <h6 class="mb-0 text-info"><i class="fas fa-calculator me-2"></i>Otros Impuestos</h6>
                                                <button type="button" class="btn btn-sm btn-outline-info" id="btnAgregarImpuesto">
                                                    <i class="fas fa-plus me-1"></i>Agregar Impuesto
                                                </button>
                                            </div>
                                            <div class="card-body p-2">
                                                <div id="contenedor-impuestos" class="table-responsive">
                                                    <div class="impuestos-vacio text-center p-4 border rounded bg-light">
                                                        <i class="fas fa-receipt fa-2x text-muted mb-2"></i>
                                                        <p class="mb-0">No hay impuestos adicionales</p>
                                                        <small class="text-muted">Haga clic en "Agregar Impuesto" para añadir</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="row mt-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                                            <i class="fas fa-save me-1"></i>Guardar Factura
                                        </button>
                                    </div>
                                </div>

                                <!-- Hidden fields para totales -->
                                <input type="hidden" id="subtotal" name="subtotal" value="0">
                                <input type="hidden" id="descuentos" name="descuentos" value="0">
                                <input type="hidden" id="no_gravado" name="no_gravado" value="0">
                                <input type="hidden" id="exento" name="exento" value="0">
                                <input type="hidden" id="impuestos" name="impuestos" value="0">
                                <input type="hidden" id="total" name="total" value="0">
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para nuevo producto rápido -->
            <div class="modal fade" id="modalNuevoProductoRapido" tabindex="-1" aria-labelledby="modalProductoRapidoLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-info bg-opacity-10">
                            <h5 class="modal-title" id="modalProductoRapidoLabel">
                                <i class="fas fa-bolt me-2 text-info"></i>Nuevo Producto Rápido
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formNuevoProductoRapido" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="producto_codigo_rapido" class="form-label small mb-1">Código *</label>
                                        <input type="text" class="form-control form-control-sm" id="producto_codigo_rapido" name="producto_codigo_rapido" maxlength="50" required>
                                        <div class="invalid-feedback small">Código obligatorio</div>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label for="producto_nombre_rapido" class="form-label small mb-1">Nombre *</label>
                                        <input type="text" class="form-control form-control-sm" id="producto_nombre_rapido" name="producto_nombre_rapido" maxlength="150" required>
                                        <div class="invalid-feedback small">Nombre obligatorio</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="producto_categoria_id_rapido" class="form-label small mb-1">Categoría *</label>
                                        <select class="form-select form-select-sm" id="producto_categoria_id_rapido" name="producto_categoria_id_rapido" required>
                                            <option value="">Seleccionar categoría</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione categoría</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="iva_alicuota_id_rapido" class="form-label small mb-1">IVA</label>
                                        <select class="form-select form-select-sm" id="iva_alicuota_id_rapido" name="iva_alicuota_id_rapido" required>
                                            <option value="">Seleccionar</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione IVA</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo_barras_rapido" class="form-label small mb-1">Código Barras</label>
                                        <input type="text" class="form-control form-control-sm" id="codigo_barras_rapido" name="codigo_barras_rapido" maxlength="150">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="unidad_medida_id_rapido" class="form-label small mb-1">Unidad Medida</label>
                                        <select class="form-select form-select-sm" id="unidad_medida_id_rapido" name="unidad_medida_id_rapido">
                                            <option value="">Seleccionar unidad</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Código del proveedor actual -->
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="card border-info">
                                            <div class="card-header py-1 bg-info bg-opacity-10">
                                                <h6 class="mb-0 small">Código del Proveedor Actual *</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control form-control-sm" id="codigo_proveedor_rapido" name="codigo_proveedor_rapido" maxlength="50" placeholder="Código que usa este proveedor" required>
                                                        <div class="invalid-feedback small">Código del proveedor obligatorio</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-text small">
                                                            Proveedor: <span id="proveedor_actual_nombre" class="fw-bold"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="producto_descripcion_rapido" class="form-label small mb-1">Descripción</label>
                                        <textarea class="form-control form-control-sm" id="producto_descripcion_rapido" name="producto_descripcion_rapido" rows="2"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardarProductoRapido">
                                <i class="fas fa-save me-1"></i>Guardar y Agregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal-xxl {
            max-width: 1400px;
        }
        @media (min-width: 1400px) {
            .modal-xxl {
                max-width: 90%;
            }
        }
        /* Quitar flechitas de los inputs number */
        .no-spinner::-webkit-inner-spin-button,
        .no-spinner::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .no-spinner {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        /* Estilo para pantalla completa */
        /* Estilo para pantalla completa */
        .modal-fullscreen {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            height: 100vh !important;
        }

        .modal-fullscreen .modal-content {
            height: 100vh !important;
            border-radius: 0 !important;
        }

        .modal-fullscreen .modal-body {
            overflow-y: auto !important;
            max-height: calc(100vh - 120px) !important;
        }

        /* Asegurar que el modal ocupe toda la pantalla */
        .modal-fullscreen {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
        }
        .modal-dialog {
            max-width: 1400px;
            width: 95%;
            margin: 1.75rem auto;
        }

        .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .modal-fullscreen .modal-body {
            max-height: calc(100vh - 120px) !important;
        }
        .modal-fullscreen .modal-dialog {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Estilo para los resultados de búsqueda */
        #resultados_busqueda {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background-color: white;
            border-radius: 4px;
        }

        #resultados_busqueda .list-group-item.active {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }

        #resultados_busqueda .list-group-item {
            cursor: pointer;
            padding: 8px 12px;
            font-size: 0.85rem;
            border-left: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
        }

        #resultados_busqueda .list-group-item:first-child {
            border-top: 1px solid #dee2e6;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
        }

        #resultados_busqueda .list-group-item:last-child {
            border-bottom: 1px solid #dee2e6;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
        }

        #resultados_busqueda .list-group-item:hover:not(.active) {
            background-color: #f8f9fa;
        }

        .table-sm td, .table-sm th {
            padding: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Estilos de validación */
        .was-validated .form-control:valid,
        .was-validated .form-select:valid,
        .form-control.is-valid,
        .form-select.is-valid {
            border-color: #28a745 !important;
            background-image: none !important;
            padding-right: 0.75rem !important;
        }

        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid,
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545 !important;
            background-image: none !important;
            padding-right: 0.75rem !important;
        }

        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 80%;
        }

        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback,
        .form-select.is-invalid ~ .invalid-feedback {
            display: block;
        }

        /* Estilos adicionales para diferenciación visual */
        .card-header {
            font-weight: 500;
        }
        
        #contenedor-detalles table {
            font-size: 0.85rem;
        }
        
        #contenedor-detalles thead {
            background-color: #e9ecef;
        }
        
        .detalles-vacio {
            color: #6c757d;
        }
        /* Asegurar que el botón de cerrar siempre sea visible */
        .modal-header .btn-close {
            background-color: rgba(0,0,0,0.1);
            border-radius: 50%;
            padding: 0.5rem;
            margin: -0.5rem -0.5rem -0.5rem auto;
        }

        .modal-header .btn-close:hover {
            background-color: rgba(0,0,0,0.2);
        }

        /* Mejorar el espaciado en pantalla completa */
        .modal-fullscreen .modal-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f8f9fa !important;
        }

        .modal-fullscreen .modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background-color: white;
        }
    </style>

    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="facturas_proveedores.js?v=<?= filemtime(__DIR__.'/facturas_proveedores.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>