<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Órdenes de Compra";
$currentPage = 'ordenes_compra';
$modudo_idx = 2;
$pagina_idx = 65;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Órdenes de Compra
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Compras</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Órdenes de Compra</li>
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
                                        <table id="tablaOrdenesCompra" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="120">Comprobante</th>
                                                    <th width="200">Proveedor</th>
                                                    <th width="100">Emisión</th>
                                                    <th width="120">Entrega Estimada</th>
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

            <!-- Modal principal - MÁS ANCHO -->
            <div class="modal fade" id="modalOrdenCompra" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h5 class="modal-title" id="modalLabel">Orden de Compra</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formOrdenCompra" class="needs-validation" novalidate>
                                <input type="hidden" id="orden_compra_id" name="orden_compra_id" />
                                
                                <!-- Cabecera compacta -->
                                <div class="row mb-2">
                                    <div class="col-md-2 mb-2">
                                        <label for="comprobante_tipo_id" class="form-label small mb-1">Tipo *</label>
                                        <select class="form-select form-select-sm" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                            <option value="">Seleccionar</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione el tipo</div>
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <label for="comprobante_letra" class="form-label small mb-1">Letra</label>
                                        <select class="form-select form-select-sm" id="comprobante_letra" name="comprobante_letra">
                                            <option value="">Letra</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="E">E</option>
                                            <option value="M">M</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <label for="comprobante_suc" class="form-label small mb-1">Sucursal</label>
                                        <input type="text" class="form-control form-control-sm" id="comprobante_suc" name="comprobante_suc" maxlength="4" placeholder="0000">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="comprobante_nro" class="form-label small mb-1">Número *</label>
                                        <input type="text" class="form-control form-control-sm" id="comprobante_nro" name="comprobante_nro" maxlength="50" required>
                                        <div class="invalid-feedback small">Número obligatorio</div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="f_emision" class="form-label small mb-1">Emisión *</label>
                                        <input type="date" class="form-control form-control-sm" id="f_emision" name="f_emision" required>
                                        <div class="invalid-feedback small">Fecha obligatoria</div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="f_entrega_estimada" class="form-label small mb-1">Entrega Est.</label>
                                        <input type="date" class="form-control form-control-sm" id="f_entrega_estimada" name="f_entrega_estimada">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="moneda_id" class="form-label small mb-1">Moneda *</label>
                                        <select class="form-select form-select-sm" id="moneda_id" name="moneda_id" required>
                                            <option value="">Seleccionar</option>
                                            <option value="1">Pesos Argentinos</option>
                                            <option value="2">Dólares USD</option>
                                            <option value="3">Euros</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione moneda</div>
                                    </div>
                                </div>

                                <!-- Segunda fila de cabecera -->
                                <div class="row mb-2">
                                    <div class="col-md-2 mb-2">
                                        <label for="tipo_cambio" class="form-label small mb-1">Tipo Cambio</label>
                                        <input type="number" class="form-control form-control-sm" id="tipo_cambio" name="tipo_cambio" step="0.000001" min="0" value="1.000000">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="entidad_id" class="form-label small mb-1">Proveedor *</label>
                                        <div class="input-group input-group-sm">
                                            <select class="form-select" id="entidad_id" name="entidad_id" required>
                                                <option value="">Seleccionar proveedor</option>
                                            </select>
                                            <button type="button" class="btn btn-outline-primary" id="btnNuevoProveedor" title="Nuevo proveedor">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback small">Seleccione proveedor</div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="entidad_sucursal_id" class="form-label small mb-1">Sucursal</label>
                                        <select class="form-select form-select-sm" id="entidad_sucursal_id" name="entidad_sucursal_id">
                                            <option value="">Seleccionar sucursal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="condicion_pago_id" class="form-label small mb-1">Condición Pago</label>
                                        <select class="form-select form-select-sm" id="condicion_pago_id" name="condicion_pago_id">
                                            <option value="">Seleccionar</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dirección y observaciones compactas -->
                                <div class="row mb-2">
                                    <div class="col-md-8 mb-2">
                                        <label for="direccion_entrega" class="form-label small mb-1">Dirección de Entrega</label>
                                        <textarea class="form-control form-control-sm" id="direccion_entrega" name="direccion_entrega" rows="1" maxlength="255"></textarea>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="observaciones" class="form-label small mb-1">Observaciones</label>
                                        <textarea class="form-control form-control-sm" id="observaciones" name="observaciones" rows="1" maxlength="255"></textarea>
                                    </div>
                                </div>

                                <!-- Sección de Detalles con más espacio -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                            <h6 class="mb-0">
                                                <i class="fas fa-list-ul me-2"></i>Detalles de Productos
                                            </h6>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-success me-1" id="btnAgregarDetalle">
                                                    <i class="fas fa-plus me-1"></i>Agregar Producto
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" id="btnNuevoProductoRapido">
                                                    <i class="fas fa-bolt me-1"></i>Nuevo Producto Rápido
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lista de detalles en línea horizontal -->
                                                                                        <!-- Modificar el contenedor de detalles en el HTML (línea ~180): -->
                               <div class="row">
                                <div class="col-12">
                                    <div id="contenedor-detalles">
                                        <!-- Los detalles se cargarán dinámicamente aquí en formato de tabla -->
                                        <div class="detalles-vacio" id="detalles-vacio">
                                            <i class="fas fa-box-open"></i>
                                            <p class="mb-0">No hay productos agregados</p>
                                            <small class="text-muted">Haga clic en "Agregar Producto" para comenzar</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <!-- Totales -->
                                <div class="row mt-4">
                                    <div class="col-md-8"></div>
                                    <div class="col-md-4">
                                        <table class="table table-sm table-borderless">
                                            <tbody>
                                                <tr>
                                                    <td><strong class="small">Subtotal:</strong></td>
                                                    <td class="text-end"><span id="subtotal_display" class="fw-bold">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong class="small">Descuentos:</strong></td>
                                                    <td class="text-end"><span id="descuentos_display">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong class="small">Impuestos:</strong></td>
                                                    <td class="text-end"><span id="impuestos_display">0.00</span></td>
                                                </tr>
                                                <tr class="table-active">
                                                    <td><strong class="small">TOTAL:</strong></td>
                                                    <td class="text-end"><span id="total_display" class="fw-bold text-primary fs-6">0.00</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <input type="hidden" id="subtotal" name="subtotal" value="0">
                                        <input type="hidden" id="descuentos" name="descuentos" value="0">
                                        <input type="hidden" id="impuestos" name="impuestos" value="0">
                                        <input type="hidden" id="total" name="total" value="0">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar Orden
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para detalle de producto -->
            <div class="modal fade" id="modalDetalleProducto" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h5 class="modal-title" id="modalDetalleLabel">Agregar Producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formDetalleProducto" class="needs-validation" novalidate>
                                <input type="hidden" id="detalle_idx" name="detalle_idx" />
                                <input type="hidden" id="detalle_id" name="detalle_id" value="0" />
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="producto_id" class="form-label small mb-1">Producto *</label>
                                        <div class="input-group input-group-sm">
                                            <select class="form-select" id="producto_id" name="producto_id" required>
                                                <option value="">Seleccionar producto</option>
                                            </select>
                                            <button type="button" class="btn btn-outline-primary" id="btnBuscarProducto" title="Buscar producto">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback small">Seleccione un producto</div>
                                        <div class="form-text small" id="producto_info"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo_proveedor" class="form-label small mb-1">Código Proveedor</label>
                                        <input type="text" class="form-control form-control-sm" id="codigo_proveedor" name="codigo_proveedor" maxlength="50">
                                        <div class="form-text small">Código del producto según proveedor</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="cantidad" class="form-label small mb-1">Cantidad *</label>
                                        <input type="number" class="form-control form-control-sm" id="cantidad" name="cantidad" step="0.01" min="0.01" required>
                                        <div class="invalid-feedback small">Ingrese la cantidad</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="precio_unitario" class="form-label small mb-1">Precio Unitario *</label>
                                        <input type="number" class="form-control form-control-sm" id="precio_unitario" name="precio_unitario" step="0.0001" min="0" required>
                                        <div class="invalid-feedback small">Ingrese el precio</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="iva_alicuota_id" class="form-label small mb-1">IVA</label>
                                        <select class="form-select form-select-sm" id="iva_alicuota_id" name="iva_alicuota_id">
                                            <option value="1">21%</option>
                                            <option value="2">10.5%</option>
                                            <option value="3">27%</option>
                                            <option value="4">0%</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="iva_porcentaje" class="form-label small mb-1">% IVA</label>
                                        <input type="number" class="form-control form-control-sm" id="iva_porcentaje" name="iva_porcentaje" step="0.01" min="0" max="100" value="21.00">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="alert alert-info p-2 mb-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small>
                                                        <strong>Neto Gravado:</strong> 
                                                        <span id="preview_neto_gravado">0.00</span> | 
                                                        <strong>IVA:</strong> 
                                                        <span id="preview_iva_importe">0.00</span>
                                                    </small>
                                                </div>
                                                <div>
                                                    <small class="fw-bold">Total Línea: $<span id="preview_total_linea">0.00</span></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardarDetalle">
                                <i class="fas fa-save me-1"></i>Agregar Producto
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para nuevo producto rápido -->
            <div class="modal fade" id="modalNuevoProductoRapido" tabindex="-1" aria-labelledby="modalProductoRapidoLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h5 class="modal-title" id="modalProductoRapidoLabel">Nuevo Producto Rápido</h5>
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
                                        <select class="form-select form-select-sm" id="iva_alicuota_id_rapido" name="iva_alicuota_id_rapido">
                                            <option value="1">21%</option>
                                            <option value="2">10.5%</option>
                                            <option value="3">27%</option>
                                            <option value="4">0%</option>
                                        </select>
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
                                                <h6 class="mb-0 small">Código del Proveedor Actual</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control form-control-sm" id="codigo_proveedor_rapido" name="codigo_proveedor_rapido" maxlength="50" placeholder="Código que usa este proveedor">
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
    .dt-buttons .btn {
        margin-right: 5px;
        margin-bottom: 5px;
    }

    .dt-button-collection .dropdown-menu {
        margin-top: 5px;
    }

    .dataTables_wrapper .dt-buttons {
        float: right;
        margin-top: 5px;
    }

    .dropdown-menu .dropdown-item i {
        width: 20px;
        text-align: center;
        margin-right: 8px;
    }

    #contenedor-detalles {
    max-height: 400px;
    overflow-y: auto;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}

.detalle-tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
}

.detalle-tabla thead {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.detalle-tabla th {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    padding: 8px 12px;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
    vertical-align: middle;
}

.detalle-tabla td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
}

.detalle-tabla tbody tr {
    transition: background-color 0.2s ease;
    border-left: 3px solid transparent;
}

.detalle-tabla tbody tr:hover {
    background-color: #f1f3f4;
    border-left-color: #0d6efd;
}

.detalle-tabla tbody tr:nth-child(even) {
    background-color: #fcfcfc;
}

.detalle-tabla tbody tr:nth-child(even):hover {
    background-color: #f1f3f4;
}

/* Columnas específicas */
.col-producto {
    width: 25%;
    max-width: 300px;
}

.col-cantidad {
    width: 10%;
    text-align: center;
}

.col-precio {
    width: 12%;
    text-align: right;
}

.col-iva {
    width: 8%;
    text-align: center;
}

.col-codigo {
    width: 15%;
}

.col-total {
    width: 12%;
    text-align: right;
    font-weight: bold;
    color: #198754;
}

.col-acciones {
    width: 10%;
    text-align: center;
}

/* Estilos para el contenido */
.producto-info {
    display: flex;
    flex-direction: column;
}

.producto-nombre {
    font-weight: 600;
    color: #212529;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.3;
}

.producto-codigo {
    font-size: 0.8rem;
    color: #6c757d;
    font-family: 'Courier New', monospace;
}

/* Estilos para números */
.cantidad-display, .precio-display, .iva-display {
    font-family: 'Courier New', monospace;
}

.precio-display {
    color: #0d6efd;
}

.total-display {
    font-weight: bold;
    font-size: 1rem;
    color: #198754;
    font-family: 'Courier New', monospace;
}

/* Acciones */
.acciones-cell {
    display: flex;
    gap: 6px;
    justify-content: center;
}

.acciones-cell .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    min-width: 36px;
}

/* Estado vacío de detalles */
.detalles-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 150px;
    color: #6c757d;
    text-align: center;
    width: 100%;
}

.detalles-vacio i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #adb5bd;
}
</style>
    <script>
        $(document).ready(function () {
            const empresa_idx = 2;
            const pagina_idx = <?php echo $pagina_idx; ?>;
            
            var tabla;
            var currentPage = 0;
            var currentOrder = [[1, 'asc']];
            var currentSearch = '';
            
            var detalles = [];
            var contadorDetalles = 0;
            var proveedorActualId = null;

            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaOrdenesCompra')) {
                    $('#tablaOrdenesCompra').DataTable().destroy();
                    $('#tablaOrdenesCompra tbody').empty();
                }

                tabla = $('#tablaOrdenesCompra').DataTable({
                    ajax: {
                        url: 'ordenes_compra_ajax.php',
                        type: 'GET',
                        data: {
                            accion: 'listar',
                            empresa_idx: empresa_idx,
                            pagina_idx: pagina_idx
                        },
                        dataSrc: ''
                    },
                    stateSave: true,
                    stateSaveParams: function (settings, data) {
                        data.page = currentPage;
                        data.order = currentOrder;

                        if (currentSearch !== '-1' && currentSearch !== '') {
                            data.search = { search: currentSearch };
                        } else {
                            data.search = { search: '' };
                        }

                        delete data.columns;
                        return data;
                    },
                    stateLoadParams: function (settings, data) {
                        if (data.page !== undefined) currentPage = data.page;
                        if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;

                        if (data.search && data.search.search !== undefined) {
                            var searchValue = data.search.search;
                            if (searchValue === '-1' || searchValue === '-1' || searchValue === '') {
                                currentSearch = '';
                            } else {
                                currentSearch = searchValue;
                            }
                        } else {
                            currentSearch = '';
                        }

                        data.search = { search: currentSearch };
                    },
                    stateLoadCallback: function (settings) {
                        var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                        if (savedData) {
                            var data = JSON.parse(savedData);

                            if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                data.search.search = '';
                            }

                            if (data.columns) {
                                $.each(data.columns, function (i, col) {
                                    if (col.search && col.search.search === '-1') {
                                        col.search.search = '';
                                    }
                                });
                            }

                            return data;
                        }
                        return null;
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                        '<"row"<"col-sm-12"tr>>' +
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                        '<"clear">',
                    pageLength: 50,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-success btn-sm',
                            title: 'Órdenes de Compra',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            className: 'btn btn-danger btn-sm',
                            title: 'Órdenes de Compra',
                            orientation: 'portrait',
                            pageSize: 'A4',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'csvHtml5',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            className: 'btn btn-primary btn-sm',
                            title: 'Ordenes_Compra',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Imprimir',
                            className: 'btn btn-secondary btn-sm',
                            title: 'Órdenes de Compra',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6],
                                stripHtml: false
                            }
                        }
                    ],
                    columns: [
                        {
                            data: 'orden_compra_id',
                            className: 'text-center fw-bold'
                        },
                        {
                            data: null,
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data.comprobante_nro;
                                }
                                return `<div class="fw-medium">${data.comprobante_nro}</div>
                                        <small class="text-muted">${data.comprobante_tipo || ''}</small>`;
                            }
                        },
                        {
                            data: null,
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data.entidad_nombre || '';
                                }
                                return `<div class="fw-medium">${data.entidad_nombre || ''}</div>
                                        <small class="text-muted">${data.entidad_fantasia || ''}</small>`;
                            }
                        },
                        {
                            data: 'f_emision',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<span class="fw-medium">${data}</span>`;
                            }
                        },
                        {
                            data: 'f_entrega_estimada',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export' || !data) {
                                    return data || '';
                                }
                                return `<span class="fw-medium">${data}</span>`;
                            }
                        },
                        {
                            data: 'total',
                            className: 'text-end',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return parseFloat(data).toFixed(2);
                                }
                                return `<span class="fw-bold text-primary">$${parseFloat(data).toFixed(2)}</span>`;
                            }
                        },
                        {
                            data: 'estado_info',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (!data || !data.estado_registro) {
                                    if (type === 'export') {
                                        return 'Sin estado';
                                    }
                                    return '<span class="badge bg-secondary">Sin estado</span>';
                                }

                                var estado = data.estado_registro;
                                var colorClass = data.bg_clase || 'bg-secondary';
                                var textClass = data.text_clase || 'text-white';

                                if (type === 'export') {
                                    return estado;
                                }

                                return `<span class="badge ${colorClass} ${textClass}">${estado}</span>`;
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            width: '250px',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return '';
                                }

                                var botones = '';

                                if (data && data.length > 0) {
                                    var editarBoton = '';
                                    var otrosBotones = '';

                                    data.forEach(boton => {
                                        var claseBoton = 'btn-sm me-1 ';
                                        if (boton.bg_clase && boton.text_clase) {
                                            claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                        } else if (boton.color_clase) {
                                            claseBoton += boton.color_clase;
                                        } else {
                                            claseBoton += 'btn-outline-primary';
                                        }

                                        var titulo = boton.descripcion || boton.nombre_funcion;
                                        var accionJs = boton.accion_js;
                                        var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                        var esConfirmable = boton.es_confirmable || 0;

                                        var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                       title="${titulo}" 
                                       data-id="${row.orden_compra_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-comprobante="${row.comprobante_nro}">
                                    ${icono}
                                </button>`;

                                        if (accionJs === 'editar') {
                                            editarBoton = botonHtml;
                                        } else {
                                            otrosBotones += botonHtml;
                                        }
                                    });

                                    botones = editarBoton + otrosBotones;
                                } else {
                                    botones = '<span class="text-muted small">Sin acciones</span>';
                                }

                                return `<div class="btn-group" role="group">${botones}</div>`;
                            }
                        }
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json',
                        buttons: {
                            excel: 'Excel',
                            pdf: 'PDF',
                            csv: 'CSV',
                            print: 'Imprimir'
                        }
                    },
                    order: currentOrder,
                    responsive: true,
                    createdRow: function (row, data, dataIndex) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'CERRADO') {
                            $(row).addClass('table-success');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'CANCELADO') {
                            $(row).addClass('table-danger');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                            $(row).addClass('table-warning');
                        }
                    },
                    initComplete: function () {
                        var buttons = new $.fn.dataTable.Buttons(tabla, {
                            buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                        }).container().appendTo($('#tablaOrdenesCompra_wrapper .col-md-6:eq(1)'));

                        $(tabla.table().container()).on('page.dt', function (e) {
                            currentPage = tabla.page();
                        });

                        $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                            currentOrder = tabla.order();
                        });

                        $(tabla.table().container()).on('search.dt', function (e, settings) {
                            currentSearch = tabla.search();
                        });

                        setTimeout(function () {
                            var searchInput = $('.dataTables_filter input');
                            if (searchInput.val() === '-1' || searchInput.val() === '') {
                                searchInput.val('');
                                currentSearch = '';

                                var savedData = localStorage.getItem('DataTables_' + tabla.settings()[0].sInstance);
                                if (savedData) {
                                    var data = JSON.parse(savedData);
                                    if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                        data.search.search = '';
                                        localStorage.setItem('DataTables_' + tabla.settings()[0].sInstance, JSON.stringify(data));
                                    }
                                }
                            }
                        }, 100);
                    }
                });

                inicializarEventos();
            }

            function inicializarEventos() {
                $('#btnRecargar').off('click').on('click', function () {
                    var btn = $(this);
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                    var savedState = {
                        page: tabla.page(),
                        order: tabla.order(),
                        search: tabla.search()
                    };

                    tabla.ajax.reload(function (json) {
                        if (savedState.page !== undefined) {
                            tabla.page(savedState.page).draw('page');
                        }
                        if (savedState.search && savedState.search !== '') {
                            tabla.search(savedState.search).draw();
                        }
                        btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                    }, false);
                });
            }

            function cargarBotonAgregar() {
                $.get('ordenes_compra_ajax.php', {
                    accion: 'obtener_boton_agregar',
                    pagina_idx: pagina_idx
                }, function (botonAgregar) {
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                        var colorClase = 'btn-primary';
                        if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                            colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                        } else if (botonAgregar.color_clase) {
                            colorClase = botonAgregar.color_clase;
                        }

                        $('#contenedor-boton-agregar').html(
                            `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                        ${icono}${botonAgregar.nombre_funcion}
                     </button>`
                        );
                    } else {
                        $('#contenedor-boton-agregar').html(
                            '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                            '<i class="fas fa-plus me-1"></i>Nueva Orden</button>'
                        );
                    }
                }, 'json');
            }

            function cargarCombosFormulario() {
                // Cargar tipos de comprobante
                $.get('ordenes_compra_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function(data) {
                    var options = '<option value="">Seleccionar</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.comprobante_tipo_id}" data-letra="${item.letra || ''}">${item.comprobante_tipo}</option>`;
                        });
                    }
                    $('#comprobante_tipo_id').html(options);
                    
                    // Cuando se selecciona un tipo, establecer la letra automáticamente si existe
                    $('#comprobante_tipo_id').change(function() {
                        var selectedOption = $(this).find('option:selected');
                        var letra = selectedOption.data('letra');
                        if (letra) {
                            $('#comprobante_letra').val(letra);
                        }
                    });
                }, 'json');

                // Cargar proveedores
                $.get('ordenes_compra_ajax.php', { accion: 'obtener_proveedores' }, function(data) {
                    var options = '<option value="">Seleccionar proveedor</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.entidad_id}">${item.entidad_nombre}</option>`;
                        });
                    }
                    $('#entidad_id').html(options);
                }, 'json');

                // Cargar condiciones de pago
                $.get('ordenes_compra_ajax.php', { 
                    accion: 'obtener_condiciones_pago',
                    empresa_idx: empresa_idx 
                }, function(data) {
                    var options = '<option value="">Seleccionar</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.condicion_pago_id}">${item.codigo} - ${item.condicion_pago}</option>`;
                        });
                    }
                    $('#condicion_pago_id').html(options);
                }, 'json');

                // Cargar productos
                cargarProductos();
            }

            function cargarProductos() {
                $.get('ordenes_compra_ajax.php', { accion: 'obtener_productos' }, function(data) {
                    var options = '<option value="">Seleccionar producto</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.producto_id}">${item.producto_codigo} - ${item.producto_nombre}</option>`;
                        });
                    }
                    $('#producto_id').html(options);
                }, 'json');
            }

            function cargarCodigoProveedor(productoId) {
                if (!proveedorActualId) {
                    $('#codigo_proveedor').val('');
                    return;
                }

                $.get('ordenes_compra_ajax.php', {
                    accion: 'obtener_codigo_proveedor',
                    producto_id: productoId,
                    entidad_id: proveedorActualId,
                    empresa_idx: empresa_idx
                }, function(res) {
                    if (res.success && res.codigo_proveedor) {
                        $('#codigo_proveedor').val(res.codigo_proveedor);
                    } else {
                        $('#codigo_proveedor').val('');
                    }
                }, 'json');
            }

            $(document).on('change', '#entidad_id', function() {
                proveedorActualId = $(this).val();
                var proveedorNombre = $(this).find('option:selected').text();
                
                $('#proveedor_actual_nombre').text(proveedorNombre || 'No seleccionado');
                
                if (proveedorActualId) {
                    $.get('ordenes_compra_ajax.php', { 
                        accion: 'obtener_sucursales',
                        entidad_id: proveedorActualId 
                    }, function(data) {
                        var options = '<option value="">Seleccionar sucursal</option>';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                            });
                        }
                        $('#entidad_sucursal_id').html(options);
                    }, 'json');
                } else {
                    $('#entidad_sucursal_id').html('<option value="">Seleccionar sucursal</option>');
                }
            });

            $(document).on('change', '#producto_id', function() {
                var productoId = $(this).val();
                if (productoId) {
                    cargarCodigoProveedor(productoId);
                } else {
                    $('#codigo_proveedor').val('');
                }
            });

           // También modificar la función cargarDatosProductoRapido:
            function cargarDatosProductoRapido() {
                $.get('ordenes_compra_ajax.php', { 
                    accion: 'obtener_categorias_productos',
                    empresa_idx: empresa_idx 
                }, function(data) {
                    var options = '<option value="">Seleccionar categoría</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.producto_categoria_id}">${item.categoria_nombre}</option>`;
                        });
                    }
                    $('#producto_categoria_id_rapido').html(options);
                }, 'json');

                $.get('ordenes_compra_ajax.php', { accion: 'obtener_unidades_medida' }, function(data) {
                    var options = '<option value="">Seleccionar unidad</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.unidad_medida_id}">${item.unidad_nombre}</option>`;
                        });
                    }
                    $('#unidad_medida_id_rapido').html(options);
                }, 'json');
            }

            $(document).on('click', '#btnNuevoProductoRapido', function() {
                if (!proveedorActualId) {
                    Swal.fire({
                        icon: "warning",
                        title: "Seleccione proveedor",
                        text: "Debe seleccionar un proveedor primero",
                        confirmButtonText: "Entendido"
                    });
                    return;
                }

                resetModalProductoRapido();
                cargarDatosProductoRapido();
                
                var modal = new bootstrap.Modal(document.getElementById('modalNuevoProductoRapido'));
                modal.show();
                $('#producto_codigo_rapido').focus();
            });

            $(document).on('click', '#btnGuardarProductoRapido', function() {
                var form = document.getElementById('formNuevoProductoRapido');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var btn = $(this);
                var originalText = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var data = {
                    accion: 'agregar_producto_rapido',
                    empresa_idx: empresa_idx,
                    producto_codigo: $('#producto_codigo_rapido').val(),
                    producto_nombre: $('#producto_nombre_rapido').val(),
                    codigo_barras: $('#codigo_barras_rapido').val(),
                    producto_descripcion: $('#producto_descripcion_rapido').val(),
                    producto_categoria_id: $('#producto_categoria_id_rapido').val(),
                    iva_alicuota_id: $('#iva_alicuota_id_rapido').val(),
                    unidad_medida_id: $('#unidad_medida_id_rapido').val() || null,
                    codigo_proveedor: $('#codigo_proveedor_rapido').val(),
                    entidad_id: proveedorActualId
                };

                $.ajax({
                    url: 'ordenes_compra_ajax.php',
                    type: 'POST',
                    data: data,
                    success: function(res) {
                        if (res.success) {
                            var modalProducto = bootstrap.Modal.getInstance(document.getElementById('modalNuevoProductoRapido'));
                            modalProducto.hide();
                            
                            cargarProductos();
                            
                            $('#producto_id').val(res.producto_id);
                            cargarCodigoProveedor(res.producto_id);
                            
                            Swal.fire({
                                icon: "success",
                                title: "Producto creado",
                                text: "El producto fue creado exitosamente",
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al crear el producto",
                                confirmButtonText: "Entendido"
                            });
                        }
                        btn.prop('disabled', false).html(originalText);
                    },
                    error: function() {
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            function resetModalProductoRapido() {
                $('#formNuevoProductoRapido')[0].reset();
                $('#formNuevoProductoRapido').removeClass('was-validated');
                $('#iva_alicuota_id_rapido').val('1');
            }

            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nueva Orden de Compra');
                cargarCombosFormulario();
                
                var today = new Date().toISOString().split('T')[0];
                $('#f_emision').val(today);

                var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                modal.show();
                $('#comprobante_nro').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var ordenId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var comprobante = $(this).data('comprobante');

                if (accionJs === 'editar') {
                    cargarOrdenParaEditar(ordenId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la orden <strong>"${comprobante}"</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionJs}`,
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            ejecutarAccion(ordenId, accionJs, comprobante);
                        }
                    });
                } else {
                    ejecutarAccion(ordenId, accionJs, comprobante);
                }
            });

            function ejecutarAccion(ordenId, accionJs, comprobante) {
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.post('ordenes_compra_ajax.php', {
                    accion: 'ejecutar_accion',
                    orden_compra_id: ordenId,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload(function (json) {
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }

                            tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                var data = this.data();
                                if (data.orden_compra_id == ordenId) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Orden "${comprobante}" actualizada correctamente`,
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                        }, false);
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionJs} la orden`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function cargarOrdenParaEditar(ordenId) {
                $.get('ordenes_compra_ajax.php', {
                    accion: 'obtener',
                    orden_compra_id: ordenId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.orden_compra_id) {
                        resetModal();
                        cargarCombosFormulario();
                        
                        $('#orden_compra_id').val(res.orden_compra_id);
                        $('#comprobante_nro').val(res.comprobante_nro);
                        $('#comprobante_letra').val(res.comprobante_letra || '');
                        $('#comprobante_suc').val(res.comprobante_suc || '');
                        $('#f_emision').val(res.f_emision);
                        $('#f_entrega_estimada').val(res.f_entrega_estimada);
                        $('#direccion_entrega').val(res.direccion_entrega);
                        $('#observaciones').val(res.observaciones);
                        $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                        $('#subtotal').val(res.subtotal);
                        $('#descuentos').val(res.descuentos);
                        $('#impuestos').val(res.impuestos);
                        $('#total').val(res.total);
                        
                        $('#subtotal_display').text(parseFloat(res.subtotal || 0).toFixed(2));
                        $('#descuentos_display').text(parseFloat(res.descuentos || 0).toFixed(2));
                        $('#impuestos_display').text(parseFloat(res.impuestos || 0).toFixed(2));
                        $('#total_display').text(parseFloat(res.total || 0).toFixed(2));
                        
                        $('#modalLabel').text('Editar Orden de Compra');

                        setTimeout(function() {
                            $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                            $('#moneda_id').val(res.moneda_id);
                            $('#condicion_pago_id').val(res.condicion_pago_id);
                            
                            if (res.entidad_id) {
                                proveedorActualId = res.entidad_id;
                                $('#entidad_id').val(res.entidad_id);
                                
                                var proveedorNombre = $('#entidad_id option:selected').text();
                                $('#proveedor_actual_nombre').text(proveedorNombre || 'No seleccionado');
                                
                                $.get('ordenes_compra_ajax.php', { 
                                    accion: 'obtener_sucursales',
                                    entidad_id: res.entidad_id 
                                }, function(data) {
                                    var options = '<option value="">Seleccionar sucursal</option>';
                                    if (data && data.length > 0) {
                                        data.forEach(function(item) {
                                            var selected = item.sucursal_id == res.entidad_sucursal_id ? 'selected' : '';
                                            options += `<option value="${item.sucursal_id}" ${selected}>${item.sucursal_nombre}</option>`;
                                        });
                                    }
                                    $('#entidad_sucursal_id').html(options);
                                    
                                    // CARGAR LOS DETALLES DESPUÉS DE TENER EL PROVEEDOR
                                    if (res.detalles && res.detalles.length > 0) {
                                        detalles = [];
                                        contadorDetalles = 0;
                                        
                                        // Para cada detalle, cargar el código del proveedor
                                        var detallesCargados = 0;
                                        var totalDetalles = res.detalles.length;
                                        
                                        res.detalles.forEach(function(detalle, index) {
                                            detalle.detalle_idx = index;
                                            
                                            // Cargar código de proveedor para cada producto
                                            if (detalle.producto_id && res.entidad_id) {
                                                $.get('ordenes_compra_ajax.php', {
                                                    accion: 'obtener_codigo_proveedor',
                                                    producto_id: detalle.producto_id,
                                                    entidad_id: res.entidad_id,
                                                    empresa_idx: empresa_idx
                                                }, function(codigoRes) {
                                                    if (codigoRes.success) {
                                                        detalle.codigo_proveedor = codigoRes.codigo_proveedor || '';
                                                    }
                                                    
                                                    detalles.push(detalle);
                                                    detallesCargados++;
                                                    
                                                    // Cuando todos los detalles estén cargados, renderizar
                                                    if (detallesCargados === totalDetalles) {
                                                        contadorDetalles = detalles.length;
                                                        renderizarDetalles();
                                                        actualizarTotales();
                                                    }
                                                }, 'json');
                                            } else {
                                                detalle.codigo_proveedor = '';
                                                detalles.push(detalle);
                                                detallesCargados++;
                                                
                                                if (detallesCargados === totalDetalles) {
                                                    contadorDetalles = detalles.length;
                                                    renderizarDetalles();
                                                    actualizarTotales();
                                                }
                                            }
                                        });
                                    }
                                }, 'json');
                            }
                            
                            // Si no hay detalles en la respuesta, inicializar array vacío
                            if (!res.detalles || res.detalles.length === 0) {
                                detalles = [];
                                contadorDetalles = 0;
                                renderizarDetalles();
                            }
                        }, 500);

                        var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos de la orden",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formOrdenCompra')[0].reset();
                $('#orden_compra_id').val('');
                $('#comprobante_letra').val('');
                $('#comprobante_suc').val('');
                $('#tipo_cambio').val('1.000000');
                $('#formOrdenCompra').removeClass('was-validated');
                
                detalles = [];
                contadorDetalles = 0;
                proveedorActualId = null;
                $('#contenedor-detalles').empty();
                
                $('#subtotal').val('0');
                $('#descuentos').val('0');
                $('#impuestos').val('0');
                $('#total').val('0');
                $('#subtotal_display').text('0.00');
                $('#descuentos_display').text('0.00');
                $('#impuestos_display').text('0.00');
                $('#total_display').text('0.00');
                
                $('#entidad_sucursal_id').html('<option value="">Seleccionar sucursal</option>');
                $('#proveedor_actual_nombre').text('No seleccionado');
            }

            $(document).on('click', '#btnAgregarDetalle', function() {
                if (!proveedorActualId) {
                    Swal.fire({
                        icon: "warning",
                        title: "Seleccione proveedor",
                        text: "Debe seleccionar un proveedor primero",
                        confirmButtonText: "Entendido"
                    });
                    return;
                }

                resetModalDetalle();
                $('#modalDetalleLabel').text('Agregar Producto');
                $('#detalle_id').val('0');
                
                var modal = new bootstrap.Modal(document.getElementById('modalDetalleProducto'));
                modal.show();
                $('#producto_id').focus();
            });

            $(document).on('click', '#btnGuardarDetalle', function() {
                var form = document.getElementById('formDetalleProducto');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var detalleIdx = $('#detalle_idx').val();
                var detalleId = $('#detalle_id').val();
                var productoId = $('#producto_id').val();
                var productoText = $('#producto_id option:selected').text();
                var cantidad = parseFloat($('#cantidad').val());
                var precioUnitario = parseFloat($('#precio_unitario').val());
                var ivaAlícuotaId = $('#iva_alicuota_id').val();
                var ivaPorcentaje = parseFloat($('#iva_porcentaje').val());
                var codigoProveedor = $('#codigo_proveedor').val();

                var netoGravado = cantidad * precioUnitario;
                var ivaImporte = netoGravado * (ivaPorcentaje / 100);
                var totalLinea = netoGravado + ivaImporte;

                var detalle = {
                    detalle_idx: detalleIdx || contadorDetalles++,
                    ordenes_compra_detalle_id: detalleId || 0,
                    producto_id: productoId,
                    producto_nombre: productoText,
                    cantidad: cantidad,
                    precio_unitario: precioUnitario,
                    iva_alicuota_id: ivaAlícuotaId,
                    iva_porcentaje: ivaPorcentaje,
                    neto_gravado: netoGravado,
                    iva_importe: ivaImporte,
                    total_linea: totalLinea,
                    codigo_proveedor: codigoProveedor
                };

                var existe = false;
                detalles.forEach(function(item, index) {
                    if (item.detalle_idx == detalleIdx) {
                        detalles[index] = detalle;
                        existe = true;
                    }
                });

                if (!existe) {
                    detalles.push(detalle);
                }

                renderizarDetalles();
                actualizarTotales();

                var modal = bootstrap.Modal.getInstance(document.getElementById('modalDetalleProducto'));
                modal.hide();

                Swal.fire({
                    icon: "success",
                    title: "Producto agregado",
                    text: "El producto fue agregado al detalle",
                    showConfirmButton: false,
                    timer: 1000,
                    toast: true,
                    position: 'top-end'
                });
            });

            function renderizarDetalles() {
                $('#contenedor-detalles').empty();
                
                if (detalles.length === 0) {
                    var htmlVacio = `
                    <div class="detalles-vacio" id="detalles-vacio">
                        <i class="fas fa-box-open"></i>
                        <p class="mb-0">No hay productos agregados</p>
                        <small class="text-muted">Haga clic en "Agregar Producto" para comenzar</small>
                    </div>`;
                    $('#contenedor-detalles').html(htmlVacio);
                    return;
                }
                
                // Crear tabla de detalles
                var html = `
                <table class="detalle-tabla">
                    <thead>
                        <tr>
                            <th class="col-producto">Producto</th>
                            <th class="col-cantidad">Cantidad</th>
                            <th class="col-precio">Precio Unitario</th>
                            <th class="col-iva">IVA %</th>
                            <th class="col-codigo">Código Proveedor</th>
                            <th class="col-total">Total Línea</th>
                            <th class="col-acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>`;
                
                detalles.forEach(function(detalle) {
                    // Extraer código y nombre del producto
                    var nombreProducto = detalle.producto_nombre;
                    var codigoProducto = '';
                    
                    // Si el nombre contiene paréntesis con código, separar
                    if (nombreProducto.includes('(')) {
                        var match = nombreProducto.match(/(.*?)\((.*?)\)/);
                        if (match) {
                            nombreProducto = match[1].trim();
                            codigoProducto = match[2].trim();
                        }
                    }
                    
                    html += `
                    <tr data-idx="${detalle.detalle_idx}">
                        <td class="col-producto">
                            <div class="producto-info">
                                <div class="producto-nombre" title="${nombreProducto}">${nombreProducto}</div>
                                ${codigoProducto ? `<div class="producto-codigo">Código: ${codigoProducto}</div>` : ''}
                            </div>
                        </td>
                        <td class="col-cantidad">
                            <span class="cantidad-display">${parseFloat(detalle.cantidad).toFixed(2)}</span>
                        </td>
                        <td class="col-precio">
                            <span class="precio-display">$${parseFloat(detalle.precio_unitario).toFixed(4)}</span>
                        </td>
                        <td class="col-iva">
                            <span class="iva-display">${parseFloat(detalle.iva_porcentaje).toFixed(2)}%</span>
                        </td>
                        <td class="col-codigo">
                            <span class="codigo-proveedor">${detalle.codigo_proveedor || 'N/A'}</span>
                        </td>
                        <td class="col-total">
                            <span class="total-display">$${parseFloat(detalle.total_linea).toFixed(2)}</span>
                        </td>
                        <td class="col-acciones">
                            <div class="acciones-cell">
                                <button type="button" class="btn btn-sm btn-warning btn-editar-detalle" 
                                        data-idx="${detalle.detalle_idx}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" 
                                        data-idx="${detalle.detalle_idx}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });
                
                html += `
                    </tbody>
                </table>`;
                
                $('#contenedor-detalles').html(html);
            }

            function actualizarTotales() {
                var subtotal = 0;
                var impuestos = 0;
                var total = 0;

                detalles.forEach(function(detalle) {
                    subtotal += detalle.neto_gravado;
                    impuestos += detalle.iva_importe;
                });

                total = subtotal + impuestos;

                $('#subtotal').val(subtotal.toFixed(2));
                $('#impuestos').val(impuestos.toFixed(2));
                $('#total').val(total.toFixed(2));
                
                $('#subtotal_display').text(subtotal.toFixed(2));
                $('#impuestos_display').text(impuestos.toFixed(2));
                $('#total_display').text(total.toFixed(2));
            }

            $(document).on('click', '.btn-editar-detalle', function() {
                var idx = $(this).data('idx');
                var detalle = detalles.find(function(item) {
                    return item.detalle_idx == idx;
                });

                if (detalle) {
                    resetModalDetalle();
                    $('#modalDetalleLabel').text('Editar Producto');
                    $('#detalle_idx').val(detalle.detalle_idx);
                    $('#detalle_id').val(detalle.ordenes_compra_detalle_id);
                    $('#producto_id').val(detalle.producto_id);
                    $('#cantidad').val(detalle.cantidad);
                    $('#precio_unitario').val(detalle.precio_unitario);
                    $('#iva_alicuota_id').val(detalle.iva_alicuota_id);
                    $('#iva_porcentaje').val(detalle.iva_porcentaje);
                    $('#codigo_proveedor').val(detalle.codigo_proveedor || '');
                    actualizarPreviewDetalle();

                    var modal = new bootstrap.Modal(document.getElementById('modalDetalleProducto'));
                    modal.show();
                }
            });

            $(document).on('click', '.btn-eliminar-detalle', function() {
                var idx = $(this).data('idx');
                
                Swal.fire({
                    title: '¿Eliminar producto?',
                    text: "Esta acción no se puede deshacer",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        detalles = detalles.filter(function(item) {
                            return item.detalle_idx != idx;
                        });
                        renderizarDetalles();
                        actualizarTotales();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: 'Producto eliminado del detalle',
                            showConfirmButton: false,
                            timer: 1000,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                });
            });

            function resetModalDetalle() {
                $('#formDetalleProducto')[0].reset();
                $('#detalle_idx').val('');
                $('#detalle_id').val('0');
                $('#iva_porcentaje').val('21.00');
                $('#formDetalleProducto').removeClass('was-validated');
                $('#preview_neto_gravado').text('0.00');
                $('#preview_iva_importe').text('0.00');
                $('#preview_total_linea').text('0.00');
                $('#codigo_proveedor').val('');
            }

            function actualizarPreviewDetalle() {
                var cantidad = parseFloat($('#cantidad').val()) || 0;
                var precio = parseFloat($('#precio_unitario').val()) || 0;
                var iva = parseFloat($('#iva_porcentaje').val()) || 0;
                
                var netoGravado = cantidad * precio;
                var ivaImporte = netoGravado * (iva / 100);
                var totalLinea = netoGravado + ivaImporte;
                
                $('#preview_neto_gravado').text(netoGravado.toFixed(2));
                $('#preview_iva_importe').text(ivaImporte.toFixed(2));
                $('#preview_total_linea').text(totalLinea.toFixed(2));
            }

            $(document).on('input', '#cantidad, #precio_unitario, #iva_porcentaje', function() {
                actualizarPreviewDetalle();
            });

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formOrdenCompra');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                if (detalles.length === 0) {
                    Swal.fire({
                        icon: "warning",
                        title: "Detalles requeridos",
                        text: "Debe agregar al menos un producto al detalle",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                }

                var id = $('#orden_compra_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                
                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var formData = $('#formOrdenCompra').serializeArray();
                var data = {};
                
                formData.forEach(function(item) {
                    data[item.name] = item.value;
                });
                
                data.detalles = JSON.stringify(detalles);
                data.empresa_idx = empresa_idx;
                data.pagina_idx = pagina_idx;
                data.accion = accionBackend;

                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.ajax({
                    url: 'ordenes_compra_ajax.php',
                    type: 'POST',
                    data: data,
                    success: function (res) {
                        if (res.resultado) {
                            tabla.ajax.reload(function (json) {
                                if (savedState.page !== undefined) {
                                    tabla.page(savedState.page).draw('page');
                                }
                                if (savedState.search && savedState.search !== '') {
                                    tabla.search(savedState.search).draw();
                                }

                                if (id) {
                                    tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                        var data = this.data();
                                        if (data.orden_compra_id == id) {
                                            $(this.node()).addClass('table-success');
                                            setTimeout(function () {
                                                $(this.node()).removeClass('table-success');
                                            }.bind(this), 2000);
                                        }
                                    });
                                }

                                btnGuardar.prop('disabled', false).html(originalText);

                                Swal.fire({
                                    icon: "success",
                                    title: "¡Guardado!",
                                    text: "Orden de compra guardada correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalOrdenCompra');
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                modal.hide();
                            }, false);
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al guardar los datos",
                                confirmButtonText: "Entendido"
                            });
                        }
                    },
                    error: function () {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            });

            $('#btnExportarExcel').click(function (e) {
                e.preventDefault();
                $('.buttons-excel').click();
            });

            $('#btnExportarPDF').click(function (e) {
                e.preventDefault();
                $('.buttons-pdf').click();
            });

            $('#btnExportarCSV').click(function (e) {
                e.preventDefault();
                $('.buttons-csv').click();
            });

            $('#btnExportarPrint').click(function (e) {
                e.preventDefault();
                $('.buttons-print').click();
            });

            inicializarDataTable();
            cargarBotonAgregar();

            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });

            $(window).on('load', function () {
                setTimeout(function () {
                    var savedData = localStorage.getItem('DataTables_tablaOrdenesCompra');
                    if (savedData) {
                        var data = JSON.parse(savedData);
                        if (data.search) {
                            if (data.search.search === '-1' || data.search.search === '') {
                                data.search.search = '';
                                localStorage.setItem('DataTables_tablaOrdenesCompra', JSON.stringify(data));
                            }
                        }
                    }
                }, 500);
            });
        });
    </script>

    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>