<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Órdenes de Compra";
$currentPage = 'ordenes_compra';
$modudo_idx = 2; // Módulo de Compras
$pagina_idx = 65; // ID de página para órdenes de compra

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
                    <small class="text-muted">Sistema de Compras Multiempresa</small>
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
                                        <!-- DataTable -->
                                        <table id="tablaOrdenesCompra" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="120">N° Orden</th>
                                                    <th width="180">Proveedor</th>
                                                    <th width="100">Fecha Emisión</th>
                                                    <th width="100">Entrega Estimada</th>
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

            <!-- Modal Principal para crear/editar orden de compra -->
            <div class="modal fade" id="modalOrdenCompra" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white py-2">
                            <h5 class="modal-title mb-0" id="modalLabel">Orden de Compra</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formOrdenCompra" class="needs-validation" novalidate>
                                <input type="hidden" id="orden_compra_id" name="orden_compra_id" />
                                <input type="hidden" id="usuario_creacion_id" name="usuario_creacion_id" value="1" />
                                
                                <!-- Datos Generales - COMPACTO -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <h6 class="mb-2 text-primary">
                                            <i class="fas fa-info-circle me-1"></i>Datos Generales
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="comprobante_id" class="form-label small fw-bold">Comprobante *</label>
                                        <select class="form-select form-select-sm" id="comprobante_id" name="comprobante_id" required>
                                            <option value="">Seleccionar...</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione un comprobante</div>
                                    </div>
                                    
                                    <div class="col-md-5 mb-2">
                                        <label for="entidad_sucursal_combo" class="form-label small fw-bold">Proveedor *</label>
                                        <select class="form-select form-select-sm" id="entidad_sucursal_combo" name="entidad_sucursal_combo" required>
                                            <option value="">Seleccionar proveedor...</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione un proveedor</div>
                                        <input type="hidden" id="entidad_id" name="entidad_id" />
                                        <input type="hidden" id="entidad_sucursal_id" name="entidad_sucursal_id" />
                                    </div>
                                    
                                    <div class="col-md-2 mb-2">
                                        <label for="fecha_emision" class="form-label small fw-bold">Fecha Emisión *</label>
                                        <input type="date" class="form-control form-control-sm" id="fecha_emision" name="fecha_emision" required>
                                        <div class="invalid-feedback small">Fecha obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-2 mb-2">
                                        <label for="fecha_entrega_estimada" class="form-label small fw-bold">Entrega Estimada</label>
                                        <input type="date" class="form-control form-control-sm" id="fecha_entrega_estimada" name="fecha_entrega_estimada">
                                    </div>
                                </div>
                                
                                <!-- Datos de Pago - COMPACTO -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <h6 class="mb-2 text-primary">
                                            <i class="fas fa-money-bill-wave me-1"></i>Datos de Pago
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="condicion_pago_id" class="form-label small fw-bold">Condición Pago</label>
                                        <select class="form-select form-select-sm" id="condicion_pago_id" name="condicion_pago_id">
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="moneda_id" class="form-label small fw-bold">Moneda *</label>
                                        <select class="form-select form-select-sm" id="moneda_id" name="moneda_id" required>
                                            <option value="">Seleccionar...</option>
                                            <option value="1">Guaraníes (PYG)</option>
                                            <option value="2">Dólares (USD)</option>
                                            <option value="3">Real (BRL)</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione una moneda</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="tipo_cambio" class="form-label small fw-bold">Tipo de Cambio</label>
                                        <input type="number" class="form-control form-control-sm" id="tipo_cambio" name="tipo_cambio" 
                                            step="0.000001" min="0" value="1.0">
                                        <div class="form-text small">Solo si moneda ≠ Guaraníes</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="deposito_destino_id" class="form-label small fw-bold">Depósito Destino</label>
                                        <select class="form-select form-select-sm" id="deposito_destino_id" name="deposito_destino_id">
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Dirección y Observaciones - COMPACTO -->
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-2">
                                        <label for="direccion_entrega" class="form-label small fw-bold">Dirección de Entrega</label>
                                        <textarea class="form-control form-control-sm" id="direccion_entrega" name="direccion_entrega" 
                                            rows="1" maxlength="255"></textarea>
                                        <div class="form-text small">Máximo 255 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-2">
                                        <label for="observaciones" class="form-label small fw-bold">Observaciones</label>
                                        <textarea class="form-control form-control-sm" id="observaciones" name="observaciones" 
                                            rows="1" maxlength="255"></textarea>
                                        <div class="form-text small">Máximo 255 caracteres</div>
                                    </div>
                                </div>
                                
                                <!-- Línea separadora -->
                                <hr class="my-3">
                                
                                <!-- Detalle de Productos -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="mb-2 text-primary">
                                            <i class="fas fa-boxes me-1"></i>Detalle de Productos
                                            <button type="button" class="btn btn-sm btn-success float-end py-1 px-2" id="btnAgregarProducto">
                                                <i class="fas fa-plus me-1 small"></i>Agregar Producto
                                            </button>
                                        </h6>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover" id="tablaDetalle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="5%">#</th>
                                                        <th width="30%">Producto</th>
                                                        <th width="10%">Cantidad</th>
                                                        <th width="15%">Precio Unit.</th>
                                                        <th width="15%">Descuento %</th>
                                                        <th width="15%">Subtotal</th>
                                                        <th width="10%" class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="detalleBody">
                                                    <!-- Filas se agregarán dinámicamente -->
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <td colspan="5" class="text-end fw-bold small">Subtotal:</td>
                                                        <td class="text-end fw-bold small" id="subtotalTotal">0.00</td>
                                                        <td></td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="5" class="text-end fw-bold small">Descuentos:</td>
                                                        <td class="text-end fw-bold small" id="descuentosTotal">0.00</td>
                                                        <td></td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="5" class="text-end fw-bold small">Impuestos:</td>
                                                        <td class="text-end fw-bold small" id="impuestosTotal">0.00</td>
                                                        <td></td>
                                                    </tr>
                                                    <tr class="table-primary">
                                                        <td colspan="5" class="text-end fw-bold">TOTAL:</td>
                                                        <td class="text-end fw-bold" id="totalOrden">0.00</td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardarOrden">
                                <i class="fas fa-save me-1"></i>Guardar Orden
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal para agregar/editar producto en el detalle -->
            <div class="modal fade" id="modalDetalleProducto" tabindex="-1" aria-labelledby="modalDetalleLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white py-2">
                            <h5 class="modal-title mb-0" id="modalDetalleLabel">Agregar Producto</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formDetalleProducto">
                                <input type="hidden" id="detalle_index" value="">
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="producto_id" class="form-label small fw-bold">Buscar Producto *</label>
                                        <div class="input-group">
                                            <select class="form-select form-select-sm" id="producto_id" name="producto_id" required
                                                data-placeholder="Busque por código o nombre...">
                                                <option value=""></option>
                                            </select>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" id="btnBuscarTodos" title="Buscar en todos los productos">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback small">Seleccione un producto</div>
                                        <div class="form-text small" id="producto_info">Primero se muestran productos del proveedor seleccionado</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-2">
                                        <label for="descripcion" class="form-label small fw-bold">Descripción Personalizada</label>
                                        <input type="text" class="form-control form-control-sm" id="descripcion" name="descripcion" 
                                            maxlength="255">
                                        <div class="form-text small">Opcional. Máx. 255 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="cantidad_pedida" class="form-label small fw-bold">Cantidad *</label>
                                        <input type="number" class="form-control form-control-sm" id="cantidad_pedida" name="cantidad_pedida" 
                                            step="0.0001" min="0.0001" required>
                                        <div class="invalid-feedback small">La cantidad es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="precio_unitario" class="form-label small fw-bold">Precio Unitario *</label>
                                        <input type="number" class="form-control form-control-sm" id="precio_unitario" name="precio_unitario" 
                                            step="0.000001" min="0" required>
                                        <div class="invalid-feedback small">El precio es obligatorio</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="descuento" class="form-label small fw-bold">Descuento %</label>
                                        <input type="number" class="form-control form-control-sm" id="descuento" name="descuento" 
                                            step="0.000001" min="0" max="100" value="0">
                                        <div class="form-text small">Porcentaje de descuento (0-100)</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-2">
                                        <label for="impuesto_id" class="form-label small fw-bold">Impuesto</label>
                                        <select class="form-select form-select-sm" id="impuesto_id" name="impuesto_id">
                                            <option value="">Sin impuesto</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Cálculos</label>
                                        <div class="bg-light p-2 rounded small">
                                            <div class="d-flex justify-content-between">
                                                <span>Subtotal:</span>
                                                <span class="fw-bold" id="previewSubtotal">0.00</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Descuento:</span>
                                                <span class="fw-bold" id="previewDescuento">0.00</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Total línea:</span>
                                                <span class="fw-bold" id="previewTotalLinea">0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Información del producto seleccionado -->
                                <div class="row mt-2" id="productoDetalleInfo" style="display: none;">
                                    <div class="col-md-12">
                                        <div class="card border-info">
                                            <div class="card-header py-1 bg-info text-white">
                                                <h6 class="mb-0"><i class="fas fa-info-circle me-1"></i>Información del Producto</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Código:</small>
                                                        <div id="infoCodigo" class="fw-bold"></div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Código Barras:</small>
                                                        <div id="infoCodigoBarras" class="fw-bold"></div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Categoría:</small>
                                                        <div id="infoCategoria" class="fw-bold"></div>
                                                    </div>
                                                    <div class="col-md-12 mt-1">
                                                        <small class="text-muted">Descripción:</small>
                                                        <div id="infoDescripcion" class="fw-bold"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success btn-sm" id="btnGuardarDetalle">
                                <i class="fas fa-check me-1"></i>Agregar Producto
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos personalizados -->
    <style>
        .detalle-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .detalle-row:hover {
            background-color: #f8f9fa;
        }
        .detalle-row.selected {
            background-color: #e3f2fd;
        }
        #tablaDetalle tbody tr td {
            vertical-align: middle;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge-estado {
            font-size: 0.85em;
            padding: 0.35em 0.65em;
        }
        .table-primary > td {
            background-color: #cfe2ff !important;
        }
        .option-group {
            font-weight: bold;
            color: #0d6efd;
            background-color: #f8f9fa;
            padding-left: 5px;
        }
        .option-sucursal {
            padding-left: 20px;
            font-style: italic;
        }
        .select2-container--bootstrap-5 .select2-selection {
            min-height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .modal-header {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .modal-body {
            padding: 1rem;
        }
        .modal-footer {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .form-control-sm, .form-select-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .form-label.small {
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .form-text.small {
            font-size: 0.75rem;
        }
        .invalid-feedback.small {
            font-size: 0.75rem;
        }
    </style>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = 2;
            const pagina_idx = <?php echo $pagina_idx; ?>;
            
            // Variables para mantener el estado del DataTable
            var tabla;
            var currentPage = 0;
            var currentOrder = [[0, 'desc']]; // Por defecto ordenado por ID descendente
            var currentSearch = '';
            
            // Variables para el detalle de productos
            var detalleProductos = [];
            var impuestosConfig = {};
            var detallesOriginales = []; // Para edición
            
            // Variable para mapear opciones del combo de proveedores
            var proveedoresMap = {};
            
            // Variable para controlar búsqueda de productos
            var productoSelectInitialized = false;
            var currentEntidadId = null;

            // Función para inicializar DataTable
            function inicializarDataTable() {
                // Destruir DataTable existente si hay uno
                if ($.fn.DataTable.isDataTable('#tablaOrdenesCompra')) {
                    $('#tablaOrdenesCompra').DataTable().destroy();
                    $('#tablaOrdenesCompra tbody').empty();
                }

                // Configuración de DataTable con botones de exportación
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
                        // Guardar página, orden y búsqueda
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
                            if (searchValue === '-1' || searchValue === '') {
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
                            className: 'text-center fw-bold',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                return `<span class="badge bg-dark">#${data}</span>`;
                            }
                        },
                        {
                            data: 'numero_orden',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                return `<span class="fw-medium">${data}</span>`;
                            }
                        },
                        {
                            data: 'proveedor_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                return `<div class="small">${data}</div><small class="text-muted">${row.proveedor_ruc || ''}</small>`;
                            }
                        },
                        {
                            data: 'fecha_emision',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                return data ? `<span class="fw-medium small">${formatDate(data)}</span>` : '';
                            }
                        },
                        {
                            data: 'fecha_entrega_estimada',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                if (!data) return '<span class="text-muted small">-</span>';
                                var today = new Date();
                                var delivery = new Date(data);
                                var diffDays = Math.ceil((delivery - today) / (1000 * 60 * 60 * 24));
                                
                                var badgeClass = 'bg-success';
                                if (diffDays < 0) {
                                    badgeClass = 'bg-danger';
                                } else if (diffDays <= 7) {
                                    badgeClass = 'bg-warning';
                                }
                                
                                return `<span class="badge ${badgeClass} small">${formatDate(data)}</span>`;
                            }
                        },
                        {
                            data: 'total',
                            className: 'text-end',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                var monedaSimbolo = row.moneda_simbolo || 'Gs.';
                                return `<span class="fw-bold small">${formatCurrency(data, monedaSimbolo)}</span>`;
                            }
                        },
                        {
                            data: 'estado_info',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (!data || !data.estado_registro) {
                                    if (type === 'export') return 'Sin estado';
                                    return '<span class="text-muted small">Sin estado</span>';
                                }
                                var estado = data.estado_registro;
                                var badgeClass = 'bg-secondary';
                                var colorClass = data.color_clase || 'btn-secondary';
                                
                                // Asignar colores según estado
                                switch(data.codigo_estandar) {
                                    case 'BORRADOR': badgeClass = 'bg-info'; break;
                                    case 'PENDIENTE': badgeClass = 'bg-warning'; break;
                                    case 'APROBADA': badgeClass = 'bg-success'; break;
                                    case 'RECHAZADA': badgeClass = 'bg-danger'; break;
                                    case 'EN_PROCESO': badgeClass = 'bg-primary'; break;
                                    case 'COMPLETADA': badgeClass = 'bg-success'; break;
                                    case 'CANCELADA': badgeClass = 'bg-dark'; break;
                                }
                                
                                if (type === 'export') return estado;
                                return `<span class="badge badge-estado ${badgeClass}">${estado}</span>`;
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            width: '250px',
                            render: function (data, type, row) {
                                if (type === 'export') return '';
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
                                           data-numero="${row.numero_orden}">
                                        ${icono}
                                    </button>`;
                                    
                                        if (accionJs === 'editar') {
                                            editarBoton = botonHtml;
                                        } else {
                                            otrosBotones += botonHtml;
                                        }
                                    });
                                    
                                    // Agregar botón para ver detalles (siempre disponible)
                                    botones = editarBoton + otrosBotones + 
                                        `<button type="button" class="btn btn-info btn-sm btn-detalle" 
                                           title="Ver Detalle" 
                                           data-id="${row.orden_compra_id}">
                                            <i class="fas fa-list"></i>
                                        </button>`;
                                } else {
                                    botones = `<button type="button" class="btn btn-info btn-sm btn-detalle" 
                                       title="Ver Detalle" 
                                       data-id="${row.orden_compra_id}">
                                        <i class="fas fa-list"></i>
                                    </button>`;
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
                        // Colores según estado
                        if (data.estado_info && data.estado_info.codigo_estandar === 'CANCELADA') {
                            $(row).addClass('table-secondary');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'RECHAZADA') {
                            $(row).addClass('table-danger');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'APROBADA') {
                            $(row).addClass('table-success');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                            $(row).addClass('table-warning');
                        }
                    },
                    initComplete: function () {
                        // Mover los botones de exportación al contenedor correcto
                        var buttons = new $.fn.dataTable.Buttons(tabla, {
                            buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                        }).container().appendTo($('#tablaOrdenesCompra_wrapper .col-md-6:eq(1)'));
                        
                        // Guardar estado actual al cambiar de página
                        $(tabla.table().container()).on('page.dt', function (e) {
                            currentPage = tabla.page();
                        });
                        
                        // Guardar estado actual al ordenar
                        $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                            currentOrder = tabla.order();
                        });
                        
                        // Guardar estado actual al buscar
                        $(tabla.table().container()).on('search.dt', function (e, settings) {
                            currentSearch = tabla.search();
                        });
                        
                        // Limpiar el campo de búsqueda si tiene "-1"
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
            
            // Función para inicializar eventos
            function inicializarEventos() {
                // Botón recargar
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
            
            // Cargar botón Agregar dinámicamente
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
            
            // Cargar datos para combos del modal principal
            function cargarCombosModal() {
                // Cargar comprobantes
                $.get('ordenes_compra_ajax.php', {
                    accion: 'cargar_comprobantes'
                }, function (comprobantes) {
                    var select = $('#comprobante_id');
                    select.empty().append('<option value="">Seleccionar...</option>');
                    if (comprobantes && comprobantes.length > 0) {
                        comprobantes.forEach(function(comp) {
                            select.append(`<option value="${comp.comprobante_fiscal_id}">${comp.codigo} - ${comp.comprobante_fiscal}</option>`);
                        });
                    }
                }, 'json');
                
                // Cargar proveedores con sucursales (un solo combo)
                $.get('ordenes_compra_ajax.php', {
                    accion: 'cargar_proveedores_sucursales',
                    empresa_idx: empresa_idx
                }, function (proveedoresData) {
                    var select = $('#entidad_sucursal_combo');
                    select.empty().append('<option value="">Seleccionar proveedor...</option>');
                    proveedoresMap = {};
                    
                    if (proveedoresData && proveedoresData.length > 0) {
                        proveedoresData.forEach(function(item) {
                            var optionValue;
                            var displayText;
                            var optionClass = '';
                            
                            if (item.tipo === 'entidad') {
                                // Es la entidad principal (sin sucursal)
                                optionValue = `entidad_${item.entidad_id}`;
                                displayText = item.entidad_nombre;
                                optionClass = 'option-group';
                            } else {
                                // Es una sucursal
                                optionValue = `sucursal_${item.entidad_id}_${item.sucursal_id}`;
                                displayText = `   └ ${item.sucursal_nombre}`;
                                if (item.sucursal_direccion) {
                                    displayText += ` - ${item.sucursal_direccion}`;
                                }
                                optionClass = 'option-sucursal';
                            }
                            
                            // Guardar mapeo para uso posterior
                            proveedoresMap[optionValue] = {
                                entidad_id: item.entidad_id,
                                entidad_sucursal_id: item.sucursal_id || null,
                                tipo: item.tipo
                            };
                            
                            select.append(`<option value="${optionValue}" class="${optionClass}">${displayText}</option>`);
                        });
                    }
                }, 'json');
                
                // Cargar condiciones de pago
                $.get('ordenes_compra_ajax.php', {
                    accion: 'cargar_condiciones_pago'
                }, function (condiciones) {
                    var select = $('#condicion_pago_id');
                    select.empty().append('<option value="">Seleccionar...</option>');
                    if (condiciones && condiciones.length > 0) {
                        condiciones.forEach(function(cond) {
                            select.append(`<option value="${cond.condicion_pago_id}">${cond.descripcion}</option>`);
                        });
                    }
                }, 'json');
                
                // Cargar depósitos
                $.get('ordenes_compra_ajax.php', {
                    accion: 'cargar_depositos'
                }, function (depositos) {
                    var select = $('#deposito_destino_id');
                    select.empty().append('<option value="">Seleccionar...</option>');
                    if (depositos && depositos.length > 0) {
                        depositos.forEach(function(dep) {
                            select.append(`<option value="${dep.deposito_id}">${dep.deposito_nombre}</option>`);
                        });
                    }
                }, 'json');
            }
            
            // Cargar datos para combos del modal de detalle
            function cargarCombosDetalle() {
                // Cargar impuestos
                $.get('ordenes_compra_ajax.php', {
                    accion: 'cargar_impuestos'
                }, function (impuestos) {
                    var select = $('#impuesto_id');
                    select.empty().append('<option value="">Sin impuesto</option>');
                    if (impuestos && impuestos.length > 0) {
                        impuestos.forEach(function(imp) {
                            impuestosConfig[imp.impuesto_id] = imp.porcentaje;
                            select.append(`<option value="${imp.impuesto_id}">${imp.descripcion} (${imp.porcentaje}%)</option>`);
                        });
                    }
                }, 'json');
                
                // Inicializar Select2 para productos (se cargarán dinámicamente)
                if (!productoSelectInitialized) {
                    $('#producto_id').select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Busque por código o nombre...',
                        allowClear: true,
                        minimumInputLength: 2,
                        language: 'es',
                        width: '100%',
                        ajax: {
                            url: 'ordenes_compra_ajax.php',
                            dataType: 'json',
                            delay: 300,
                            data: function (params) {
                                return {
                                    accion: 'buscar_productos',
                                    search: params.term,
                                    entidad_id: currentEntidadId,
                                    empresa_idx: empresa_idx,
                                    tipo_busqueda: 'proveedor' // Por defecto busca en productos del proveedor
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data.map(function(item) {
                                        return {
                                            id: item.producto_id,
                                            text: `${item.producto_codigo} - ${item.producto_nombre}`,
                                            data: item
                                        };
                                    })
                                };
                            },
                            cache: true
                        },
                        templateResult: function (producto) {
                            if (producto.loading) {
                                return producto.text;
                            }
                            
                            var $container = $(
                                '<div class="select2-result-repository clearfix">' +
                                    '<div class="select2-result-repository__title fw-bold"></div>' +
                                    '<div class="select2-result-repository__description small text-muted"></div>' +
                                '</div>'
                            );
                            
                            $container.find('.select2-result-repository__title').text(producto.text);
                            
                            var desc = '';
                            if (producto.data && producto.data.codigo_proveedor) {
                                desc += `Código Proveedor: ${producto.data.codigo_proveedor}`;
                            }
                            if (producto.data && producto.data.costo) {
                                if (desc) desc += ' | ';
                                desc += `Costo: ${producto.data.costo}`;
                            }
                            
                            if (desc) {
                                $container.find('.select2-result-repository__description').text(desc);
                            }
                            
                            return $container;
                        }
                    });
                    
                    productoSelectInitialized = true;
                }
            }
            
            // Manejador para selección en combo de proveedores
            $(document).on('change', '#entidad_sucursal_combo', function() {
                var selectedValue = $(this).val();
                var entidadInput = $('#entidad_id');
                var sucursalInput = $('#entidad_sucursal_id');
                
                if (selectedValue && proveedoresMap[selectedValue]) {
                    var data = proveedoresMap[selectedValue];
                    entidadInput.val(data.entidad_id);
                    sucursalInput.val(data.entidad_sucursal_id);
                    
                    // Actualizar variable global para búsqueda de productos
                    currentEntidadId = data.entidad_id;
                    
                    // Actualizar texto informativo
                    $('#producto_info').text(`Mostrando productos del proveedor seleccionado. Use el botón 🔍 para buscar en todos los productos.`);
                } else {
                    entidadInput.val('');
                    sucursalInput.val('');
                    currentEntidadId = null;
                    $('#producto_info').text('Seleccione un proveedor primero');
                }
                
                // Limpiar selección de productos
                $('#producto_id').val(null).trigger('change');
                $('#productoDetalleInfo').hide();
            });
            
            // Manejador para botón de búsqueda en todos los productos
            $(document).on('click', '#btnBuscarTodos', function() {
                if (!currentEntidadId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Seleccione Proveedor',
                        text: 'Primero debe seleccionar un proveedor',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }
                
                var searchTerm = $('#producto_id').select2('data')[0] ? $('#producto_id').select2('data')[0].text : '';
                
                // Abrir modal de búsqueda avanzada
                Swal.fire({
                    title: 'Buscar en Todos los Productos',
                    html: `
                        <div class="mb-3">
                            <label for="swalSearchTerm" class="form-label small fw-bold">Término de búsqueda:</label>
                            <input type="text" class="form-control form-control-sm" id="swalSearchTerm" value="${searchTerm}" 
                                placeholder="Ingrese código o nombre del producto...">
                        </div>
                        <div class="alert alert-info small py-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Esta búsqueda incluirá todos los productos del sistema, no solo los asociados al proveedor.
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Buscar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const term = document.getElementById('swalSearchTerm').value;
                        if (!term) {
                            Swal.showValidationMessage('Ingrese un término de búsqueda');
                            return false;
                        }
                        return term;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        buscarTodosLosProductos(result.value);
                    }
                });
            });
            
            // Función para buscar en todos los productos
            function buscarTodosLosProductos(searchTerm) {
                $.ajax({
                    url: 'ordenes_compra_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'buscar_productos',
                        search: searchTerm,
                        entidad_id: currentEntidadId,
                        empresa_idx: empresa_idx,
                        tipo_busqueda: 'todos'
                    },
                    success: function(productos) {
                        if (productos.length === 0) {
                            Swal.fire({
                                icon: 'info',
                                title: 'No se encontraron productos',
                                text: 'No hay productos que coincidan con su búsqueda',
                                confirmButtonText: 'Entendido'
                            });
                            return;
                        }
                        
                        // Mostrar resultados en un modal
                        var html = `
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        
                        productos.forEach(function(producto) {
                            html += `
                                <tr>
                                    <td class="fw-bold">${producto.producto_codigo}</td>
                                    <td>${producto.producto_nombre}</td>
                                    <td class="small">${producto.producto_descripcion || '-'}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-seleccionar-producto" 
                                            data-id="${producto.producto_id}"
                                            data-codigo="${producto.producto_codigo}"
                                            data-nombre="${producto.producto_nombre}"
                                            data-descripcion="${producto.producto_descripcion || ''}"
                                            data-codigo-barras="${producto.codigo_barras || ''}"
                                            data-categoria="${producto.categoria_nombre || ''}">
                                            <i class="fas fa-check"></i> Seleccionar
                                        </button>
                                    </td>
                                </tr>`;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-warning small mt-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Este producto no está asociado al proveedor. Será agregado a la orden pero deberá asociarlo después.
                            </div>`;
                        
                        Swal.fire({
                            title: 'Resultados de Búsqueda',
                            html: html,
                            width: '900px',
                            showConfirmButton: false,
                            showCloseButton: true
                        });
                        
                        // Manejador para seleccionar producto
                        $(document).on('click', '.btn-seleccionar-producto', function() {
                            var productoId = $(this).data('id');
                            var productoCodigo = $(this).data('codigo');
                            var productoNombre = $(this).data('nombre');
                            var productoDescripcion = $(this).data('descripcion');
                            var codigoBarras = $(this).data('codigo-barras');
                            var categoria = $(this).data('categoria');
                            
                            // Asignar al select2
                            var newOption = new Option(`${productoCodigo} - ${productoNombre}`, productoId, true, true);
                            $('#producto_id').append(newOption).trigger('change');
                            
                            // Cerrar modal de resultados
                            Swal.close();
                            
                            // Mostrar información del producto
                            mostrarInfoProducto({
                                producto_codigo: productoCodigo,
                                producto_nombre: productoNombre,
                                producto_descripcion: productoDescripcion,
                                codigo_barras: codigoBarras,
                                categoria_nombre: categoria
                            });
                            
                            Swal.fire({
                                icon: 'info',
                                title: 'Producto no asociado',
                                html: `El producto <strong>${productoCodigo} - ${productoNombre}</strong> no está asociado a este proveedor.<br>
                                       <small>Considere asociarlo en la gestión de productos del proveedor.</small>`,
                                confirmButtonText: 'Entendido'
                            });
                        });
                    }
                });
            }
            
            // Manejador para cambio en selección de producto
            $(document).on('change', '#producto_id', function() {
                var productoId = $(this).val();
                var selectedData = $(this).select2('data')[0];
                
                if (productoId && selectedData && selectedData.data) {
                    mostrarInfoProducto(selectedData.data);
                    
                    // Si tiene costo del proveedor, sugerirlo como precio
                    if (selectedData.data.costo) {
                        $('#precio_unitario').val(selectedData.data.costo);
                        actualizarPreviewCalculos();
                    }
                } else {
                    $('#productoDetalleInfo').hide();
                }
            });
            
            // Función para mostrar información del producto seleccionado
            function mostrarInfoProducto(productoData) {
                $('#infoCodigo').text(productoData.producto_codigo || '');
                $('#infoCodigoBarras').text(productoData.codigo_barras || '');
                $('#infoCategoria').text(productoData.categoria_nombre || '');
                $('#infoDescripcion').text(productoData.producto_descripcion || productoData.producto_nombre || '');
                $('#productoDetalleInfo').show();
            }
            
            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nueva Orden de Compra');
                detalleProductos = [];
                detallesOriginales = [];
                actualizarTotales();
                cargarCombosModal();
                cargarCombosDetalle();
                
                // Establecer fecha actual por defecto
                var today = new Date().toISOString().split('T')[0];
                $('#fecha_emision').val(today);
                
                var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                modal.show();
                $('#comprobante_id').focus();
            });
            
            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var ordenId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var numeroOrden = $(this).data('numero');
                
                if (accionJs === 'editar') {
                    cargarOrdenParaEditar(ordenId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la orden <strong>"${numeroOrden}"</strong>?`,
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
                            ejecutarAccion(ordenId, accionJs, numeroOrden);
                        }
                    });
                } else {
                    ejecutarAccion(ordenId, accionJs, numeroOrden);
                }
            });
            
            // Manejador para botón ver detalle
            $(document).on('click', '.btn-detalle', function () {
                var ordenId = $(this).data('id');
                verDetalleOrden(ordenId);
            });
            
            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(ordenId, accionJs, numeroOrden) {
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
                                text: res.message || `Orden "${numeroOrden}" actualizada correctamente`,
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
            
            // Función para ver detalle de orden (modal de solo lectura)
            function verDetalleOrden(ordenId) {
                $.get('ordenes_compra_ajax.php', {
                    accion: 'obtener_detalle',
                    orden_compra_id: ordenId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.orden) {
                        var proveedorInfo = res.orden.entidad_nombre;
                        if (res.orden.sucursal_nombre) {
                            proveedorInfo += ' - ' + res.orden.sucursal_nombre;
                        }
                        
                        var html = `
                            <div class="container-fluid">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="text-primary">Orden #${res.orden.numero_orden}</h5>
                                        <p class="mb-1"><strong>Proveedor:</strong> ${proveedorInfo}</p>
                                        <p class="mb-1"><strong>CUIT:</strong> ${res.orden.cuit || 'No informado'}</p>
                                        <p class="mb-1"><strong>Fecha Emisión:</strong> ${formatDate(res.orden.fecha_emision)}</p>
                                        <p class="mb-1"><strong>Estado:</strong> <span class="badge bg-info">${res.orden.estado_registro}</span></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="border-bottom pb-2">Detalle de Productos</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th class="text-center">Cantidad</th>
                                                        <th class="text-end">Precio Unit.</th>
                                                        <th class="text-end">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>`;
                        
                        if (res.detalles && res.detalles.length > 0) {
                            res.detalles.forEach(function(detalle) {
                                html += `
                                    <tr>
                                        <td>${detalle.producto_nombre || detalle.descripcion || 'Producto'}</td>
                                        <td class="text-center">${detalle.cantidad_pedida}</td>
                                        <td class="text-end">${formatCurrency(detalle.precio_unitario)}</td>
                                        <td class="text-end">${formatCurrency(detalle.subtotal)}</td>
                                    </tr>`;
                            });
                        } else {
                            html += `<tr><td colspan="4" class="text-center text-muted">No hay productos en esta orden</td></tr>`;
                        }
                        
                        html += `
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                                                        <td class="text-end fw-bold">${formatCurrency(res.orden.subtotal)}</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                                        <td class="text-end fw-bold fs-5">${formatCurrency(res.orden.total)}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        
                        Swal.fire({
                            title: 'Detalle de Orden',
                            html: html,
                            width: '800px',
                            showConfirmButton: false,
                            showCloseButton: true
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener el detalle de la orden",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }
            
            // Función para cargar orden en modal de edición
            function cargarOrdenParaEditar(ordenId) {
                $.get('ordenes_compra_ajax.php', {
                    accion: 'obtener',
                    orden_compra_id: ordenId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.orden && res.detalles) {
                        resetModal();
                        
                        // Cargar combos primero
                        cargarCombosModal();
                        cargarCombosDetalle();
                        
                        // Llenar datos principales después de cargar combos
                        setTimeout(function() {
                            $('#orden_compra_id').val(res.orden.orden_compra_id);
                            $('#comprobante_id').val(res.orden.comprobante_id);
                            
                            // Seleccionar el proveedor/sucursal correcto en el combo
                            var comboValue = '';
                            if (res.orden.entidad_sucursal_id) {
                                // Tiene sucursal seleccionada
                                comboValue = `sucursal_${res.orden.entidad_id}_${res.orden.entidad_sucursal_id}`;
                            } else {
                                // Solo tiene entidad (sin sucursal)
                                comboValue = `entidad_${res.orden.entidad_id}`;
                            }
                            
                            // Asignar valores a los campos ocultos
                            $('#entidad_id').val(res.orden.entidad_id);
                            $('#entidad_sucursal_id').val(res.orden.entidad_sucursal_id || '');
                            
                            // Seleccionar en el combo
                            $('#entidad_sucursal_combo').val(comboValue);
                            currentEntidadId = res.orden.entidad_id;
                            
                            $('#fecha_emision').val(res.orden.fecha_emision);
                            $('#fecha_entrega_estimada').val(res.orden.fecha_entrega_estimada || '');
                            $('#condicion_pago_id').val(res.orden.condicion_pago_id || '');
                            $('#moneda_id').val(res.orden.moneda_id);
                            $('#tipo_cambio').val(res.orden.tipo_cambio || '1.0');
                            $('#deposito_destino_id').val(res.orden.deposito_destino_id || '');
                            $('#direccion_entrega').val(res.orden.direccion_entrega || '');
                            $('#observaciones').val(res.orden.observaciones || '');
                            
                            // Guardar detalles originales
                            detallesOriginales = res.detalles;
                            detalleProductos = res.detalles.map(function(det) {
                                return {
                                    producto_id: det.producto_id,
                                    descripcion: det.descripcion || '',
                                    cantidad_pedida: det.cantidad_pedida,
                                    precio_unitario: det.precio_unitario,
                                    descuento: det.descuento,
                                    impuesto_id: det.impuesto_id || null,
                                    subtotal: det.subtotal
                                };
                            });
                            
                            actualizarTablaDetalle();
                            actualizarTotales();
                            
                            $('#modalLabel').text('Editar Orden de Compra #' + res.orden.numero_orden);
                            
                            var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                            modal.show();
                        }, 500);
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
            
            // Función para actualizar tabla de detalle
            function actualizarTablaDetalle() {
                var tbody = $('#detalleBody');
                tbody.empty();
                
                if (detalleProductos.length === 0) {
                    tbody.append(`
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                No hay productos agregados
                            </td>
                        </tr>`);
                    return;
                }
                
                detalleProductos.forEach(function(item, index) {
                    var subtotalLinea = item.cantidad_pedida * item.precio_unitario;
                    var descuentoMonto = subtotalLinea * (item.descuento / 100);
                    var subtotalConDescuento = subtotalLinea - descuentoMonto;
                    
                    tbody.append(`
                        <tr class="detalle-row" data-index="${index}">
                            <td class="text-center small">${index + 1}</td>
                            <td>
                                <div class="fw-medium small">${item.producto_nombre || item.descripcion || 'Producto #' + item.producto_id}</div>
                                <small class="text-muted">Código: ${item.producto_codigo || item.producto_id}</small>
                            </td>
                            <td class="text-center small">${formatNumber(item.cantidad_pedida, 4)}</td>
                            <td class="text-end small">${formatCurrency(item.precio_unitario)}</td>
                            <td class="text-end small">${item.descuento}%</td>
                            <td class="text-end fw-bold small">${formatCurrency(subtotalConDescuento)}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-editar-detalle" 
                                    data-index="${index}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-detalle" 
                                    data-index="${index}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`);
                });
            }
            
            // Función para actualizar totales
            function actualizarTotales() {
                var subtotal = 0;
                var descuentos = 0;
                var impuestos = 0;
                
                detalleProductos.forEach(function(item) {
                    var subtotalLinea = item.cantidad_pedida * item.precio_unitario;
                    var descuentoMonto = subtotalLinea * (item.descuento / 100);
                    var subtotalConDescuento = subtotalLinea - descuentoMonto;
                    
                    subtotal += subtotalConDescuento;
                    descuentos += descuentoMonto;
                    
                    // Calcular impuestos si hay
                    if (item.impuesto_id && impuestosConfig[item.impuesto_id]) {
                        var tasaImpuesto = impuestosConfig[item.impuesto_id] / 100;
                        impuestos += subtotalConDescuento * tasaImpuesto;
                    }
                });
                
                var total = subtotal + impuestos;
                
                $('#subtotalTotal').text(formatCurrency(subtotal));
                $('#descuentosTotal').text(formatCurrency(descuentos));
                $('#impuestosTotal').text(formatCurrency(impuestos));
                $('#totalOrden').text(formatCurrency(total));
            }
            
            // Manejador para agregar producto al detalle
            $(document).on('click', '#btnAgregarProducto', function () {
                if (!currentEntidadId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Seleccione Proveedor',
                        text: 'Primero debe seleccionar un proveedor',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }
                
                $('#modalDetalleLabel').text('Agregar Producto');
                $('#detalle_index').val('');
                $('#formDetalleProducto')[0].reset();
                $('#producto_id').val(null).trigger('change');
                $('#productoDetalleInfo').hide();
                actualizarPreviewCalculos();
                
                var modal = new bootstrap.Modal(document.getElementById('modalDetalleProducto'));
                modal.show();
                
                // Enfocar el campo de búsqueda
                setTimeout(function() {
                    $('#producto_id').select2('open');
                }, 500);
            });
            
            // Manejador para editar producto del detalle
            $(document).on('click', '.btn-editar-detalle', function () {
                var index = $(this).data('index');
                var item = detalleProductos[index];
                
                if (item) {
                    $('#modalDetalleLabel').text('Editar Producto');
                    $('#detalle_index').val(index);
                    $('#descripcion').val(item.descripcion || '');
                    $('#cantidad_pedida').val(item.cantidad_pedida);
                    $('#precio_unitario').val(item.precio_unitario);
                    $('#descuento').val(item.descuento);
                    $('#impuesto_id').val(item.impuesto_id || '');
                    
                    // Buscar y seleccionar el producto en el select2
                    var searchUrl = 'ordenes_compra_ajax.php?accion=buscar_producto_por_id&producto_id=' + item.producto_id + '&empresa_idx=' + empresa_idx;
                    
                    $.ajax({
                        url: searchUrl,
                        type: 'GET',
                        success: function(productoData) {
                            if (productoData) {
                                var newOption = new Option(
                                    productoData.producto_codigo + ' - ' + productoData.producto_nombre,
                                    item.producto_id,
                                    true,
                                    true
                                );
                                $('#producto_id').append(newOption).trigger('change');
                                mostrarInfoProducto(productoData);
                            }
                        }
                    });
                    
                    actualizarPreviewCalculos();
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalDetalleProducto'));
                    modal.show();
                }
            });
            
            // Manejador para eliminar producto del detalle
            $(document).on('click', '.btn-eliminar-detalle', function () {
                var index = $(this).data('index');
                var item = detalleProductos[index];
                
                Swal.fire({
                    title: '¿Eliminar Producto?',
                    html: `¿Está seguro de eliminar el producto <strong>${item.producto_nombre || item.descripcion || 'Producto'}</strong> del detalle?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        detalleProductos.splice(index, 1);
                        actualizarTablaDetalle();
                        actualizarTotales();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: 'Producto eliminado del detalle',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
                });
            });
            
            // Actualizar preview de cálculos en tiempo real
            $('#cantidad_pedida, #precio_unitario, #descuento').on('input', function() {
                actualizarPreviewCalculos();
            });
            
            // Función para actualizar preview de cálculos
            function actualizarPreviewCalculos() {
                var cantidad = parseFloat($('#cantidad_pedida').val()) || 0;
                var precio = parseFloat($('#precio_unitario').val()) || 0;
                var descuento = parseFloat($('#descuento').val()) || 0;
                
                var subtotal = cantidad * precio;
                var descuentoMonto = subtotal * (descuento / 100);
                var total = subtotal - descuentoMonto;
                
                $('#previewSubtotal').text(formatCurrency(subtotal));
                $('#previewDescuento').text(formatCurrency(descuentoMonto));
                $('#previewTotalLinea').text(formatCurrency(total));
            }
            
            // Manejador para guardar detalle de producto
            $(document).on('click', '#btnGuardarDetalle', function () {
                var form = document.getElementById('formDetalleProducto');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }
                
                var productoId = $('#producto_id').val();
                if (!productoId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Producto Requerido',
                        text: 'Debe seleccionar un producto',
                        confirmButtonText: 'Entendido'
                    });
                    $('#producto_id').select2('open');
                    return false;
                }
                
                var selectedData = $('#producto_id').select2('data')[0];
                if (!selectedData) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo obtener información del producto seleccionado',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                var index = $('#detalle_index').val();
                var cantidad = parseFloat($('#cantidad_pedida').val());
                var precio = parseFloat($('#precio_unitario').val());
                var descuento = parseFloat($('#descuento').val()) || 0;
                var descripcion = $('#descripcion').val();
                var impuestoId = $('#impuesto_id').val() || null;
                
                var productoData = selectedData.data || {};
                var productoCodigo = productoData.producto_codigo || '';
                var productoNombre = productoData.producto_nombre || selectedData.text.split(' - ')[1] || '';
                
                var item = {
                    producto_id: productoId,
                    producto_codigo: productoCodigo,
                    producto_nombre: productoNombre,
                    descripcion: descripcion,
                    cantidad_pedida: cantidad,
                    precio_unitario: precio,
                    descuento: descuento,
                    impuesto_id: impuestoId,
                    subtotal: cantidad * precio * (1 - descuento/100)
                };
                
                if (index === '') {
                    // Nuevo item
                    detalleProductos.push(item);
                } else {
                    // Editar item existente
                    detalleProductos[index] = item;
                }
                
                actualizarTablaDetalle();
                actualizarTotales();
                
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalDetalleProducto'));
                modal.hide();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: 'Producto agregado al detalle',
                    showConfirmButton: false,
                    timer: 1500
                });
            });
            
            // Función para resetear el modal
            function resetModal() {
                $('#formOrdenCompra')[0].reset();
                $('#orden_compra_id').val('');
                $('#entidad_id').val('');
                $('#entidad_sucursal_id').val('');
                $('#entidad_sucursal_combo').val('');
                currentEntidadId = null;
                $('#detalleBody').empty();
                detalleProductos = [];
                detallesOriginales = [];
                actualizarTotales();
                $('#formOrdenCompra').removeClass('was-validated');
            }
            
            // Validación del formulario principal
            $('#btnGuardarOrden').click(function () {
                var form = document.getElementById('formOrdenCompra');
                
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }
                
                if (detalleProductos.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Detalle Vacío',
                        text: 'Debe agregar al menos un producto al detalle',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                var entidadId = $('#entidad_id').val();
                if (!entidadId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Proveedor Requerido',
                        text: 'Debe seleccionar un proveedor',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                var id = $('#orden_compra_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                
                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
                
                // Preparar datos del detalle para enviar
                var detallesParaEnviar = detalleProductos.map(function(item) {
                    return {
                        producto_id: item.producto_id,
                        descripcion: item.descripcion,
                        cantidad_pedida: item.cantidad_pedida,
                        precio_unitario: item.precio_unitario,
                        descuento: item.descuento,
                        impuesto_id: item.impuesto_id
                    };
                });
                
                // Preparar datos principales
                var formData = {
                    orden_compra_id: id,
                    comprobante_id: $('#comprobante_id').val(),
                    entidad_id: $('#entidad_id').val(),
                    entidad_sucursal_id: $('#entidad_sucursal_id').val() || null,
                    fecha_emision: $('#fecha_emision').val(),
                    fecha_entrega_estimada: $('#fecha_entrega_estimada').val() || null,
                    condicion_pago_id: $('#condicion_pago_id').val() || null,
                    moneda_id: $('#moneda_id').val(),
                    tipo_cambio: $('#tipo_cambio').val() || 1.0,
                    deposito_destino_id: $('#deposito_destino_id').val() || null,
                    direccion_entrega: $('#direccion_entrega').val() || '',
                    observaciones: $('#observaciones').val() || '',
                    subtotal: $('#subtotalTotal').text().replace(/[^0-9.-]+/g, ''),
                    descuentos: $('#descuentosTotal').text().replace(/[^0-9.-]+/g, ''),
                    impuestos: $('#impuestosTotal').text().replace(/[^0-9.-]+/g, ''),
                    total: $('#totalOrden').text().replace(/[^0-9.-]+/g, ''),
                    usuario_creacion_id: $('#usuario_creacion_id').val(),
                    detalles: JSON.stringify(detallesParaEnviar),
                    detalles_originales: id ? JSON.stringify(detallesOriginales) : '[]',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                };
                
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };
                
                $.ajax({
                    url: 'ordenes_compra_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        ...formData
                    },
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
            
            // Manejadores para exportación
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
            
            // Funciones de utilidad
            function formatDate(dateString) {
                if (!dateString) return '';
                var date = new Date(dateString);
                return date.toLocaleDateString('es-ES');
            }
            
            function formatCurrency(amount, symbol) {
                symbol = symbol || 'Gs. ';
                return symbol + parseFloat(amount).toLocaleString('es-ES', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
            
            function formatNumber(number, decimals) {
                return parseFloat(number).toLocaleString('es-ES', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }
            
            // Inicializar
            inicializarDataTable();
            cargarBotonAgregar();
            
            // Agregar tooltips
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });
            
            // Limpiar localStorage si tiene el bug del "-1"
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

    <!-- Librerías necesarias -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>