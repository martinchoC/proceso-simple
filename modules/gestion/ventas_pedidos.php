<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

// Obtener parámetros de la URL
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 0;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Pedidos de Venta";
$currentPage = 'ventas_pedidos';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-shopping-bag me-2"></i>Pedidos de Venta
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Ventas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pedidos de Venta</li>
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
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                            <div id="contenedor-boton-agregar" class="d-inline">
                                                <!-- Cargando... -->
                                                <button type="button" class="btn btn-primary" id="btnNuevo" style="display:none;">
                                                    <i class="fas fa-spinner fa-spin me-1"></i>Cargando...
                                                </button>
                                            </div>
                                        </div>
                                            <div class="col-md-2">
                                                <input type="text" class="form-control form-control-sm" id="filtro_cliente" placeholder="Filtrar por cliente...">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="text" class="form-control form-control-sm" id="filtro_estado" placeholder="Filtrar por estado...">
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dataTables_length" id="tablaVentasPedidos_length"></div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dataTables_filter" id="tablaVentasPedidos_filter"></div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcel" title="Exportar a Excel">
                                                        <i class="fas fa-file-excel"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDF" title="Exportar a PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportarCSV" title="Exportar a CSV">
                                                        <i class="fas fa-file-csv"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarPrint" title="Imprimir tabla">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <table id="tablaVentasPedidos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="100">Tipo</th>
                                                    <th width="120">Sucursal</th>
                                                    <th width="120">Punto Venta</th>
                                                    <th width="120">Número</th>
                                                    <th width="200">Cliente</th>
                                                    <th width="100">Emisión</th>
                                                    <th width="120">Entrega Est.</th>
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

            <!-- ============================================================ -->
            <!-- MODAL PRINCIPAL - VERSIÓN MEJORADA                            -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalVentaPedido" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <!-- HEADER -->
                        <div class="modal-header bg-gradient-primary text-white py-2">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-shopping-bag me-2"></i>Pedido de Venta
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-light me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body p-3">
                            <form id="formVentaPedido" class="needs-validation" novalidate>
                                <!-- Campos ocultos -->
                                <input type="hidden" id="venta_pedido_id" name="venta_pedido_id" />
                                <input type="hidden" id="producto_iva_id" name="producto_iva_id" />
                                <input type="hidden" id="entidad_id" name="entidad_id" />
                                <input type="hidden" id="entidad_sucursal_id" name="entidad_sucursal_id" />
                                <input type="hidden" id="empresa_id_hidden" name="empresa_id_hidden" value="<?= $empresa_id ?>" />
                                <input type="hidden" id="pagina_id_hidden" name="pagina_id_hidden" value="<?= $pagina_id ?>" />

                                <!-- ==================== CABECERA ==================== -->
                                <div class="card card-primary card-outline card-tabs mb-3">
                                    <div class="card-header p-0 pt-1 border-bottom-0">
                                        <ul class="nav nav-tabs" id="cabeceraTabs" role="tablist">
                                            <li class="nav-item">
                                                <a class="nav-link active" id="tab-datos" data-bs-toggle="tab" href="#datos" role="tab">
                                                    <i class="fas fa-file-invoice me-1"></i>Datos del Pedido
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" id="tab-productos" data-bs-toggle="tab" href="#productos" role="tab">
                                                    <i class="fas fa-boxes me-1"></i>Productos
                                                    <span class="badge bg-primary rounded-pill ms-1" id="contador-productos">0</span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" id="tab-totales" data-bs-toggle="tab" href="#totales" role="tab">
                                                    <i class="fas fa-calculator me-1"></i>Totales
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-body">
                                        <div class="tab-content">
                                            <!-- TAB 1: DATOS -->
                                            <div class="tab-pane fade show active" id="datos" role="tabpanel">
                                                <div class="row g-3">
                                                    <!-- Columna izquierda -->
                                                    <div class="col-md-6">
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <label for="sucursal_id" class="form-label fw-bold small">Sucursal *</label>
                                                                <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                                                    <option value="">Seleccionar sucursal</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione sucursal</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="punto_venta_id" class="form-label fw-bold small">Punto de Venta *</label>
                                                                <select class="form-select form-select-sm" id="punto_venta_id" name="punto_venta_id" required>
                                                                    <option value="">Primero seleccione sucursal</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione punto de venta</div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="entidad_combo" class="form-label fw-bold small">Cliente / Sucursal *</label>
                                                                <select class="form-select form-select-sm" id="entidad_combo" name="entidad_combo" required>
                                                                    <option value="">Seleccionar cliente o sucursal</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione cliente o sucursal</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="comprobante_tipo_id" class="form-label fw-bold small">Tipo Comprobante *</label>
                                                                <select class="form-select form-select-sm" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                                                    <option value="">Seleccionar</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione el tipo</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="comprobante_nro" class="form-label fw-bold small">Número</label>
                                                                <div class="input-group input-group-sm">
                                                                    <input type="number" class="form-control form-control-sm" id="comprobante_nro" name="comprobante_nro" value="0" min="1" readonly>
                                                                    <span class="input-group-text bg-light"><i class="fas fa-hashtag"></i></span>
                                                                </div>
                                                                <small class="text-muted">Se asigna al confirmar</small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Columna derecha -->
                                                    <div class="col-md-6">
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <label for="f_emision" class="form-label fw-bold small">Fecha Emisión *</label>
                                                                <input type="date" class="form-control form-control-sm" id="f_emision" name="f_emision" required>
                                                                <div class="invalid-feedback small">Fecha obligatoria</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="f_entrega_estimada" class="form-label fw-bold small">Entrega Estimada</label>
                                                                <input type="date" class="form-control form-control-sm" id="f_entrega_estimada" name="f_entrega_estimada">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="moneda_id" class="form-label fw-bold small">Moneda *</label>
                                                                <select class="form-select form-select-sm" id="moneda_id" name="moneda_id" required>
                                                                    <option value="">Seleccionar</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione moneda</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="tipo_cambio" class="form-label fw-bold small">Tipo Cambio</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" id="tipo_cambio" name="tipo_cambio" step="0.000001" value="1.000000">
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="condicion_pago_id" class="form-label fw-bold small">Condición de Pago</label>
                                                                <select class="form-select form-select-sm" id="condicion_pago_id" name="condicion_pago_id">
                                                                    <option value="">Seleccionar</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Dirección y observaciones -->
                                                <div class="row g-2 mt-2">
                                                    <div class="col-md-8">
                                                        <label for="direccion_entrega" class="form-label fw-bold small">Dirección de Entrega</label>
                                                        <input type="text" class="form-control form-control-sm" id="direccion_entrega" name="direccion_entrega" maxlength="255" placeholder="Calle, número, localidad, etc.">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label for="observaciones" class="form-label fw-bold small">Observaciones</label>
                                                        <input type="text" class="form-control form-control-sm" id="observaciones" name="observaciones" maxlength="255" placeholder="Notas adicionales">
                                                    </div>
                                                </div>

                                                <!-- Resumen de totales (mismo dato/formato que la solapa Totales) -->
                                                <div class="row g-3 mt-1">
                                                    <div class="col-12">
                                                        <div class="row g-2">
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-secondary"><i class="fas fa-coins"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Bruto</span>
                                                                        <span class="info-box-number" id="bruto_display_resumen">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-danger"><i class="fas fa-circle-minus"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Descuento</span>
                                                                        <span class="info-box-number" id="descuento_display_resumen">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-success"><i class="fas fa-calculator"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Total Neto</span>
                                                                        <span class="info-box-number" id="total_neto_display_resumen">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-warning"><i class="fas fa-percent"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Impuestos</span>
                                                                        <span class="info-box-number" id="impuestos_display_resumen">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="card bg-primary text-white">
                                                            <div class="card-body py-3">
                                                                <div class="row align-items-center">
                                                                    <div class="col-md-6">
                                                                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>TOTAL DEL PEDIDO</h5>
                                                                    </div>
                                                                    <div class="col-md-6 text-md-end">
                                                                        <h2 class="mb-0" id="total_display_resumen">$0.00</h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- TAB 2: PRODUCTOS -->
                                            <div class="tab-pane fade" id="productos" role="tabpanel">
                                                <!-- Barra de búsqueda y agregado -->
                                                <div class="card card-info card-outline mb-3">
                                                    <div class="card-header py-1 bg-info bg-opacity-10">
                                                        <h6 class="mb-0 small"><i class="fas fa-plus-circle me-2"></i>Agregar Producto</h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-md-3 position-relative">
                                                                <label class="small fw-bold">Producto</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                    id="busqueda_producto" 
                                                                    placeholder="Buscar por código o nombre..."
                                                                    autocomplete="off">
                                                                <input type="hidden" id="producto_seleccionado_id">
                                                <input type="hidden" id="producto_codigo_seleccionado">
                                                <input type="hidden" id="producto_nombre_seleccionado">
                                                                <div id="resultados_busqueda" class="list-group position-absolute" style="z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%; display: none; background: white; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
                                                            </div>
                                                            <div class="col-md-1">
                                                                <label class="small fw-bold">Cant.</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_cantidad" step="0.01" min="0.01" value="1.00">
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="small fw-bold">Precio</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_precio" step="0.0001" min="0" placeholder="0.00" readonly>
                                                            </div>
                                                            <div class="col-md-1">
                                                                <label class="small fw-bold">IVA %</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner"
                                                                    id="producto_iva" step="0.01" min="0" value="0.00" readonly>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="small fw-bold">IVA Importe</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_iva_importe" step="0.01" min="0" value="0.00" readonly>
                                                            </div>
                                                            <div class="col-md-1">
                                                                <label class="small fw-bold">No Grav.</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_no_gravado" step="0.01" min="0" value="0.00">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <label class="small fw-bold">Exento</label>
                                                                <input type="number" class="form-control form-control-sm no-spinner" 
                                                                    id="producto_exento" step="0.01" min="0" value="0.00">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <button type="button" class="btn btn-sm btn-success w-100" id="btnAgregarProducto">
                                                                    <i class="fas fa-plus"></i>
                                                                    <span class="d-none d-md-inline ms-1">Agregar</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Tabla de detalles -->
                                                <div class="table-responsive">
                                                    <div id="contenedor-detalles">
                                                        <div class="detalles-vacio text-center p-4 border rounded bg-light">
                                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                            <p class="mb-0 fw-bold">No hay productos agregados</p>
                                                            <small class="text-muted">Seleccione un producto para comenzar</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Botón para nuevo producto rápido -->
                                                <div class="mt-2 text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" id="btnNuevoProductoRapido">
                                                        <i class="fas fa-bolt me-1"></i>Nuevo Producto Rápido
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- TAB 3: TOTALES -->
                                            <div class="tab-pane fade" id="totales" role="tabpanel">
                                                <div class="row g-3">
                                                    <!-- Resumen de totales en tarjetas -->
                                                    <div class="col-12">
                                                        <div class="row g-2">
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-success"><i class="fas fa-calculator"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Total Neto</span>
                                                                        <span class="info-box-number" id="total_neto_display">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-secondary"><i class="fas fa-circle-minus"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">No Gravado</span>
                                                                        <span class="info-box-number" id="no_gravado_display">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-info"><i class="fas fa-circle-exclamation"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Exento</span>
                                                                        <span class="info-box-number" id="exento_display">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 col-md-6">
                                                                <div class="info-box bg-light">
                                                                    <span class="info-box-icon bg-warning"><i class="fas fa-percent"></i></span>
                                                                    <div class="info-box-content">
                                                                        <span class="info-box-text fw-bold">Impuestos</span>
                                                                        <span class="info-box-number" id="impuestos_display">$0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Total general destacado -->
                                                    <div class="col-12">
                                                        <div class="card bg-primary text-white">
                                                            <div class="card-body py-3">
                                                                <div class="row align-items-center">
                                                                    <div class="col-md-6">
                                                                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>TOTAL DEL PEDIDO</h5>
                                                                    </div>
                                                                    <div class="col-md-6 text-md-end">
                                                                        <h2 class="mb-0" id="total_display">$0.00</h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Campos ocultos para enviar -->
                                                <input type="hidden" id="total_neto" name="total_neto" value="0">
                                                <input type="hidden" id="descuento_general_pct" name="descuento_general_pct" value="0">
                                                <input type="hidden" id="descuentos" name="descuentos" value="0">
                                                <input type="hidden" id="no_gravado" name="no_gravado" value="0">
                                                <input type="hidden" id="exento" name="exento" value="0">
                                                <input type="hidden" id="impuestos" name="impuestos" value="0">
                                                <input type="hidden" id="total" name="total" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ==================== BOTONES DE ACCIÓN ==================== -->
                                <div class="row mt-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                                            <i class="fas fa-save me-1"></i>Guardar Pedido
                                        </button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- MODAL PRODUCTO RÁPIDO                                         -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalNuevoProductoRapido" tabindex="-1" aria-labelledby="modalProductoRapidoLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white py-2">
                            <h5 class="modal-title" id="modalProductoRapidoLabel">
                                <i class="fas fa-bolt me-2"></i>Nuevo Producto Rápido
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formNuevoProductoRapido" class="needs-validation" novalidate>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="producto_codigo_rapido" class="form-label fw-bold small">Código *</label>
                                        <input type="text" class="form-control form-control-sm" id="producto_codigo_rapido" name="producto_codigo_rapido" maxlength="50" required>
                                        <div class="invalid-feedback small">Código obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="producto_categoria_id_rapido" class="form-label fw-bold small">Categoría *</label>
                                        <select class="form-select form-select-sm" id="producto_categoria_id_rapido" name="producto_categoria_id_rapido" required>
                                            <option value="">Seleccionar categoría</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione categoría</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="producto_nombre_rapido" class="form-label fw-bold small">Nombre *</label>
                                        <input type="text" class="form-control form-control-sm" id="producto_nombre_rapido" name="producto_nombre_rapido" maxlength="150" required>
                                        <div class="invalid-feedback small">Nombre obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="codigo_barras_rapido" class="form-label fw-bold small">Código de Barras</label>
                                        <input type="text" class="form-control form-control-sm" id="codigo_barras_rapido" name="codigo_barras_rapido" maxlength="150">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="iva_alicuota_id_rapido" class="form-label fw-bold small">IVA</label>
                                        <select class="form-select form-select-sm" id="iva_alicuota_id_rapido" name="iva_alicuota_id_rapido" required>
                                            <option value="">Seleccionar</option>
                                        </select>
                                        <div class="invalid-feedback small">Seleccione IVA</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="unidad_medida_id_rapido" class="form-label fw-bold small">Unidad de Medida</label>
                                        <select class="form-select form-select-sm" id="unidad_medida_id_rapido" name="unidad_medida_id_rapido">
                                            <option value="">Seleccionar unidad</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="producto_descripcion_rapido" class="form-label fw-bold small">Descripción</label>
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

    <!-- ============================================================ -->
    <!-- ESTILOS MEJORADOS                                             -->
    <!-- ============================================================ -->
    <style>
        /* ===== ESTILOS GENERALES ===== */
        :root {
            --primary-gradient: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #d39e00 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #0f7a8a 100%);
        }

        .bg-gradient-primary {
            background: var(--primary-gradient) !important;
        }

        /* ===== MODAL ===== */
        .modal-fullscreen .modal-dialog {
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
            max-height: calc(100vh - 120px);
        }

        /* ===== TABS ===== */
        .card-tabs .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            background: #f8f9fa;
            border-radius: 4px 4px 0 0;
        }
        .card-tabs .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            padding: 0.6rem 1.2rem;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .card-tabs .nav-tabs .nav-link:hover {
            background: rgba(0,0,0,0.03);
            color: #1a73e8;
        }
        .card-tabs .nav-tabs .nav-link.active {
            color: #1a73e8;
            background: transparent;
            border-bottom: 3px solid #1a73e8;
        }
        .card-tabs .nav-tabs .nav-link .badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }

        /* ===== FORMULARIOS ===== */
        .form-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
            margin-bottom: 0.2rem;
        }
        .form-control-sm, .form-select-sm {
            border-radius: 4px;
            border-color: #ced4da;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control-sm:focus, .form-select-sm:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 0.2rem rgba(26, 115, 232, 0.15);
        }

        /* ===== INFO BOX ===== */
        .info-box {
            display: flex;
            align-items: stretch;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
            background: #f8f9fa;
        }
        .info-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .info-box .info-box-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 8px;
            font-size: 1.2rem;
            color: white;
            flex-shrink: 0;
            margin-right: 0.75rem;
        }
        .info-box .info-box-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .info-box .info-box-text {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .info-box .info-box-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #212529;
        }

        /* ===== TABLA DE DETALLES ===== */
        #contenedor-detalles {
            font-size: 0.85rem;
        }
        #contenedor-detalles table {
            border-radius: 8px;
            overflow: hidden;
        }
        #contenedor-detalles thead th {
            background: #f1f3f5 !important;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 0.5rem 0.75rem;
        }
        #contenedor-detalles tbody td {
            padding: 0.4rem 0.75rem;
            vertical-align: middle;
        }
        #contenedor-detalles tbody tr:hover {
            background: #f8f9fa;
        }
        #contenedor-detalles tbody tr.table-info {
            background: #e3f2fd !important;
        }

        .detalles-vacio {
            color: #6c757d;
            border-radius: 8px;
            background: #f8f9fa !important;
            border: 2px dashed #dee2e6 !important;
        }

        /* ===== RESULTADOS BÚSQUEDA ===== */
        #resultados_busqueda {
            border-radius: 8px !important;
            box-shadow: 0 6px 24px rgba(0,0,0,0.15) !important;
            border: 1px solid #e9ecef !important;
            background: white !important;
        }
        #resultados_busqueda .list-group-item {
            padding: 0.6rem 1rem;
            border: none;
            border-bottom: 1px solid #f1f3f5;
            cursor: pointer;
            transition: background 0.15s ease;
            font-size: 0.85rem;
        }
        #resultados_busqueda .list-group-item:last-child {
            border-bottom: none;
        }
        #resultados_busqueda .list-group-item:hover {
            background: #f1f3f5;
        }
        #resultados_busqueda .list-group-item.active {
            background: #1a73e8;
            color: white;
        }

        /* ===== INPUTS NUMBER ===== */
        .no-spinner::-webkit-inner-spin-button,
        .no-spinner::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .no-spinner {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        /* ===== VALIDACIÓN ===== */
        .was-validated .form-control:valid,
        .was-validated .form-select:valid {
            border-color: #28a745 !important;
            background-image: none !important;
        }
        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid {
            border-color: #dc3545 !important;
            background-image: none !important;
        }
        .invalid-feedback {
            font-size: 0.7rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .modal-body {
                padding: 0.75rem !important;
            }
            .card-tabs .nav-tabs .nav-link {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }
            .info-box .info-box-number {
                font-size: 1rem;
            }
            .info-box .info-box-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            #contenedor-detalles table {
                font-size: 0.75rem;
            }
            #contenedor-detalles thead th,
            #contenedor-detalles tbody td {
                padding: 0.25rem 0.4rem;
            }
        }

        @media (max-width: 576px) {
            .card-tabs .nav-tabs .nav-link {
                font-size: 0.7rem;
                padding: 0.3rem 0.5rem;
            }
            .info-box {
                padding: 0.4rem;
            }
            .info-box .info-box-number {
                font-size: 0.9rem;
            }
            .modal-footer .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }

        /* ===== DATA TABLE OVERRIDES ===== */
        .column-filter {
            width: 100%;
            padding: 0.25rem 0.4rem;
            font-size: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-top: 0.25rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .column-filter:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 0.2rem rgba(26, 115, 232, 0.15);
            outline: none;
        }
        #tablaVentasPedidos thead th {
            vertical-align: top;
            padding-bottom: 0.5rem;
        }
        .dt-buttons {
            display: none !important;
        }
        #tablaVentasPedidos_length,
        #tablaVentasPedidos_filter {
            margin: 0;
            padding: 0;
        }
        #tablaVentasPedidos_length label,
        #tablaVentasPedidos_filter label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            gap: 5px;
            font-size: 0.85rem;
        }
        #tablaVentasPedidos_length select {
            width: auto;
            display: inline-block;
            margin: 0 5px;
        }
        #tablaVentasPedidos_filter input {
            width: 200px;
            margin-left: 5px;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            display: none !important;
        }
        .dataTables_paginate .paginate_button {
            padding: 0.2rem 0.5rem;
            font-size: 0.8rem;
        }
        .btn-accion {
            margin-bottom: 2px;
        }

        /* ===== RESPONSIVE TABLA ===== */
        @media (max-width: 768px) {
            .card-header .row > div {
                margin-bottom: 8px;
                text-align: center !important;
            }
            .card-header .col-md-2,
            .card-header .col-md-3,
            .card-header .col-md-4 {
                width: 100%;
            }
            #tablaVentasPedidos_filter label,
            #tablaVentasPedidos_length label {
                justify-content: center;
            }
            #tablaVentasPedidos_filter input {
                width: 150px;
            }
            .btn-group {
                justify-content: center;
                flex-wrap: wrap;
            }
            #tablaVentasPedidos th:nth-child(3),
            #tablaVentasPedidos td:nth-child(3),
            #tablaVentasPedidos th:nth-child(4),
            #tablaVentasPedidos td:nth-child(4) {
                display: none;
            }
            #contenedor-detalles table th:nth-child(4),
            #contenedor-detalles table td:nth-child(4),
            #contenedor-detalles table th:nth-child(5),
            #contenedor-detalles table td:nth-child(5),
            #contenedor-detalles table th:nth-child(6),
            #contenedor-detalles table td:nth-child(6),
            #contenedor-detalles table th:nth-child(7),
            #contenedor-detalles table td:nth-child(7) {
                display: none;
            }
        }
        @media (max-width: 480px) {
            #tablaVentasPedidos th:nth-child(6),
            #tablaVentasPedidos td:nth-child(6) {
                display: none;
            }
            #tablaVentasPedidos th,
            #tablaVentasPedidos td {
                font-size: 0.7rem !important;
                padding: 0.2rem 0.3rem !important;
            }
            .btn-sm {
                padding: 0.15rem 0.3rem;
                font-size: 0.65rem;
            }
            .dataTables_paginate .paginate_button {
                padding: 0.1rem 0.3rem;
                font-size: 0.7rem;
            }
        }

        /* ===== SCROLL PERSONALIZADO ===== */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        .modal-body::-webkit-scrollbar-track {
            background: #f1f3f5;
            border-radius: 4px;
        }
        .modal-body::-webkit-scrollbar-thumb {
            background: #adb5bd;
            border-radius: 4px;
        }
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #868e96;
        }

        /* ===== BADGE DE CONTADOR ===== */
        #contador-productos {
            transition: all 0.3s ease;
        }
        #contador-productos.bg-primary {
            background: #1a73e8 !important;
        }
        #contador-productos.bg-success {
            background: #28a745 !important;
        }
        #contador-productos.bg-warning {
            background: #ffc107 !important;
            color: #212529 !important;
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
    
    <script>
        const PAGINA_ID = <?= $pagina_id ?>;
        const EMPRESA_ID = <?= $empresa_id ?>;
        const MODULO_ID = <?= $modulo_id ?>;
    </script>
    <script src="ventas_pedidos.js?v=<?= filemtime(__DIR__.'/ventas_pedidos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>