<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Órdenes de Compra";
$currentPage = 'ordenes_compra';
$modudo_idx = 2;
$pagina_idx = 51;

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

            <!-- Modal principal -->
           <div class="modal fade" id="modalOrdenCompra" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h5 class="modal-title" id="modalLabel">Orden de Compra</h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                    <div class="modal-body p-3">
                            <form id="formOrdenCompra" class="needs-validation" novalidate>
                                <input type="hidden" id="orden_compra_id" name="orden_compra_id" />
                                
                                <!-- Primera fila de cabecera -->
                                <div class="row mb-2">
                                    <div class="col-md-3 mb-2">
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
                                    <div class="col-md-2 mb-2">
                                        <label for="sucursal_id" class="form-label small mb-1">Sucursal *</label>
                                        <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">Seleccionar sucursal</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione sucursal</div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="comprobante_tipo_id" class="form-label small mb-1">Tipo *</label>
                                        <select class="form-select form-select-sm" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                            <option value="">Seleccionar</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione el tipo</div>
                                    </div>
                                    <div class="col-md-1 mb-2">
                                        <label for="comprobante_pv" class="form-label small mb-1">PV *</label>
                                        <input type="number" class="form-control form-control-sm" id="comprobante_pv" name="comprobante_pv" value="0" min="0" required>
                                        <div class="invalid-feedback small">Obligatorio</div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="comprobante_nro" class="form-label small mb-1">Número *</label>
                                        <input type="number" class="form-control form-control-sm" id="comprobante_nro" name="comprobante_nro" value="0" min="1" required>
                                        <div class="invalid-feedback small">Número obligatorio</div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="f_emision" class="form-label small mb-1">Emisión *</label>
                                        <input type="date" class="form-control form-control-sm" id="f_emision" name="f_emision" required>
                                        <div class="invalid-feedback small">Fecha obligatoria</div>
                                    </div>
                                </div>

                                <!-- Segunda fila de cabecera -->
                                <div class="row mb-2">
                                    <div class="col-md-3 mb-2">
                                        <label for="entidad_sucursal_id" class="form-label small mb-1">Sucursal Proveedor</label>
                                        <select class="form-select form-select-sm" id="entidad_sucursal_id" name="entidad_sucursal_id">
                                            <option value="">Seleccionar sucursal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="f_entrega_estimada" class="form-label small mb-1">Entrega Est.</label>
                                        <input type="date" class="form-control form-control-sm" id="f_entrega_estimada" name="f_entrega_estimada" min="">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="moneda_id" class="form-label small mb-1">Moneda *</label>
                                        <select class="form-select form-select-sm" id="moneda_id" name="moneda_id" required>
                                            <option value="">Seleccionar</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione moneda</div>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label for="tipo_cambio" class="form-label small mb-1">Tipo Cambio</label>
                                        <input type="number" class="form-control form-control-sm" id="tipo_cambio" name="tipo_cambio" step="0.000001" min="0" value="1.000000">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="condicion_pago_id" class="form-label small mb-1">Condición Pago</label>
                                        <select class="form-select form-select-sm" id="condicion_pago_id" name="condicion_pago_id">
                                            <option value="">Seleccionar</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dirección y observaciones -->
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

                                <!-- Título de sección de productos -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                            <h6 class="mb-0">
                                                <i class="fas fa-list-ul me-2"></i>Detalles de Productos
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-outline-info" id="btnNuevoProductoRapido">
                                                <i class="fas fa-bolt me-1"></i>Nuevo Producto Rápido
                                            </button>
                                        </div>
                                    </div>
                                </div>

                               <!-- Fila de agregado rápido de productos -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="card card-primary card-outline">
                                            <div class="card-header py-2 bg-light">
                                                <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Producto</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-3 position-relative">
                                                        <label class="small mb-1">Producto</label>
                                                        <input type="text" class="form-control form-control-sm" 
                                                            id="busqueda_producto" 
                                                            placeholder="Buscar producto..."
                                                            autocomplete="off">
                                                        <input type="hidden" id="producto_seleccionado_id">
                                                        <div id="resultados_busqueda" class="list-group position-absolute" style="z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%; display: none;"></div>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="small mb-1">Cant.</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" 
                                                            id="producto_cantidad" 
                                                            step="0.01" min="0.01" value="1.00">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="small mb-1">Precio</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" 
                                                            id="producto_precio" 
                                                            step="0.0001" min="0">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="small mb-1">IVA %</label>
                                                        <select class="form-select form-select-sm" id="producto_iva">
                                                            <option value="21">21%</option>
                                                            <option value="10.5">10.5%</option>
                                                            <option value="27">27%</option>
                                                            <option value="0">0%</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="small mb-1">IVA Importe</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" 
                                                            id="producto_iva_importe" 
                                                            step="0.01" min="0" value="0.00">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="small mb-1">No Grav.</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" 
                                                            id="producto_no_gravado" 
                                                            step="0.01" min="0" value="0.00">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="small mb-1">Exento</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" 
                                                            id="producto_exento" 
                                                            step="0.01" min="0" value="0.00">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="small mb-1">&nbsp;</label>
                                                        <button type="button" class="btn btn-sm btn-success w-100" id="btnAgregarProducto">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lista de detalles -->
                                <div class="row">
                                    <div class="col-12">
                                        <div id="contenedor-detalles">
                                            <!-- Los detalles se cargarán dinámicamente aquí -->
                                            <div class="detalles-vacio" id="detalles-vacio">
                                                <i class="fas fa-box-open"></i>
                                                <p class="mb-0">No hay productos agregados</p>
                                                <small class="text-muted">Seleccione un producto para comenzar</small>
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
        .modal-fullscreen {
            max-width: 100%;
            margin: 0;
            height: 100vh;
        }

        .modal-fullscreen .modal-content {
            height: 100vh;
            border-radius: 0;
        }

        .modal-fullscreen .modal-body {
            overflow-y: auto;
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
    
    <script src="ordenes_compra.js?v=<?= filemtime(__DIR__.'/ordenes_compra.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>