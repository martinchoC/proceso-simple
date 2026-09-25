<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

// Obtener parámetros de la URL
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 0;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;
// Deep-link: permite abrir esta pantalla directo en modo edición de un remito
// puntual (usado por la solapa "Remitos" del módulo de pedidos, vía target="_blank").
$venta_remito_id_inicial = isset($_GET['venta_remito_id']) ? intval($_GET['venta_remito_id']) : 0;

$pageTitle = "Remitos de Venta";
$currentPage = 'ventas_remitos';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-truck-loading me-2"></i>Remitos de Venta
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Ventas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Remitos de Venta</li>
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
                                                <div class="dataTables_length" id="tablaVentasRemitos_length"></div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dataTables_filter" id="tablaVentasRemitos_filter"></div>
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
                                        <table id="tablaVentasRemitos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="100">Tipo</th>
                                                    <th width="120">Sucursal</th>
                                                    <th width="120">Punto Venta</th>
                                                    <th width="120">Número</th>
                                                    <th width="200">Cliente</th>
                                                    <th width="100">Emisión</th>
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
            <!-- MODAL PRINCIPAL                                               -->
            <!-- ============================================================ -->
            <div class="modal fade" id="modalVentaRemito" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <!-- HEADER -->
                        <div class="modal-header bg-gradient-primary text-white py-2">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-truck-loading me-2"></i>Remito de Venta
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
                            <form id="formVentaRemito" class="needs-validation" novalidate>
                                <!-- Campos ocultos -->
                                <input type="hidden" id="venta_remito_id" name="venta_remito_id" />
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
                                                    <i class="fas fa-file-invoice me-1"></i>Datos del Remito
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" id="tab-productos" data-bs-toggle="tab" href="#productos" role="tab">
                                                    <i class="fas fa-boxes me-1"></i>Productos
                                                    <span class="badge bg-primary rounded-pill ms-1" id="contador-productos">0</span>
                                                </a>
                                            </li>
                                            <!-- Solo visible cuando el remito está Pend. de Facturación o Facturación
                                                 Parcial (lo decide JS según tabla_estado_registro_id al abrir el modal) -->
                                            <li class="nav-item" id="tab-item-facturar" style="display: none;">
                                                <a class="nav-link" id="tab-facturar" data-bs-toggle="tab" href="#facturar" role="tab">
                                                    <i class="fas fa-file-invoice-dollar me-1"></i>Facturar
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
                                                                <label for="punto_venta_id" class="form-label fw-bold small">Punto de Venta *</label>
                                                                <select class="form-select form-select-sm" id="punto_venta_id" name="punto_venta_id" required>
                                                                    <option value="">Seleccionar punto de venta</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione punto de venta</div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="comprobante_tipo_id" class="form-label fw-bold small">Tipo Comprobante *</label>
                                                                <select class="form-select form-select-sm" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                                                    <option value="">Seleccionar</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione el tipo</div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="entidad_combo" class="form-label fw-bold small">Cliente / Sucursal *</label>
                                                                <select class="form-select form-select-sm" id="entidad_combo" name="entidad_combo" required>
                                                                    <option value="">Seleccionar cliente o sucursal</option>
                                                                </select>
                                                                <div class="invalid-feedback small">Seleccione cliente o sucursal</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Columna derecha -->
                                                    <div class="col-md-6">
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <label for="comprobante_nro" class="form-label fw-bold small">Número</label>
                                                                <div class="input-group input-group-sm">
                                                                    <input type="number" class="form-control form-control-sm" id="comprobante_nro" name="comprobante_nro" value="0" min="1" readonly>
                                                                    <span class="input-group-text bg-light"><i class="fas fa-hashtag"></i></span>
                                                                </div>
                                                                <small class="text-muted">Se asigna al confirmar</small>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="f_emision" class="form-label fw-bold small">Fecha Emisión *</label>
                                                                <input type="date" class="form-control form-control-sm" id="f_emision" name="f_emision" required>
                                                                <div class="invalid-feedback small">Fecha obligatoria</div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label for="observaciones" class="form-label fw-bold small">Observaciones</label>
                                                                <input type="text" class="form-control form-control-sm" id="observaciones" name="observaciones" maxlength="255" placeholder="Notas adicionales">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Resumen -->
                                                <div class="row g-3 mt-1">
                                                    <div class="col-12">
                                                        <div class="card bg-primary text-white">
                                                            <div class="card-body py-3">
                                                                <div class="row align-items-center">
                                                                    <div class="col-md-7">
                                                                        <h5 class="mb-2"><i class="fas fa-boxes-stacked me-2"></i>TOTAL DEL REMITO</h5>
                                                                        <div class="small opacity-75">
                                                                            Subtotal: $<span id="subtotal_bruto_resumen">0.00</span>
                                                                            &nbsp;|&nbsp; Descuento: $<span id="descuento_resumen">0.00</span>
                                                                            &nbsp;|&nbsp; Neto: $<span id="neto_resumen">0.00</span>
                                                                            &nbsp;|&nbsp; IVA: $<span id="iva_resumen">0.00</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-5 text-md-end">
                                                                        <h2 class="mb-0" id="total_display_resumen">$0.00</h2>
                                                                        <small class="opacity-75">Total con IVA</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- TAB 2: PRODUCTOS -->
                                            <div class="tab-pane fade" id="productos" role="tabpanel">

                                                <!-- Pedidos pendientes del cliente (se oculta si no hay ninguno) -->
                                                <div class="card card-warning card-outline mb-3" id="card-pedidos-pendientes" style="display: none;">
                                                    <div class="card-header py-1 bg-warning bg-opacity-10">
                                                        <h6 class="mb-0 small"><i class="fas fa-clock me-2"></i>Pedidos pendientes de entrega del cliente</h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div id="contenedor-pendientes"></div>
                                                    </div>
                                                </div>

                                                <!-- Agregar producto sin pedido -->
                                                <div class="card card-info card-outline mb-3">
                                                    <div class="card-header py-1 bg-info bg-opacity-10">
                                                        <h6 class="mb-0 small"><i class="fas fa-plus-circle me-2"></i>Agregar producto sin pedido</h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="row g-2">
                                                            <div class="col-12">
                                                                <label class="small fw-bold">Producto</label>
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                                    <div class="form-control p-1" id="busqueda_producto_container" style="min-height: 38px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; cursor: text;">
                                                                        <input type="text" id="busqueda_producto" class="border-0 flex-grow-1" style="min-width: 100px; outline: none; padding: 4px 8px; font-size: 14px;" autocomplete="off" placeholder="Escribí palabras y presioná espacio...">
                                                                    </div>
                                                                    <button type="button" class="btn btn-outline-secondary" id="btnLimpiarTagsProductoLibre" title="Limpiar filtro">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">
                                                                    Presioná <kbd>Espacio</kbd> para agregar una etiqueta (se combinan por código, nombre y compatibilidad).
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <div id="resultados_busqueda" class="mt-2"></div>
                                                        <small class="text-muted d-block mt-1" id="descuento_general_info">
                                                            <i class="fas fa-tag me-1"></i>Seleccione un cliente para ver su descuento general.
                                                        </small>
                                                    </div>
                                                </div>

                                                <!-- Tabla de detalles del remito -->
                                                <div class="table-responsive">
                                                    <div id="contenedor-detalles">
                                                        <div class="detalles-vacio text-center p-4 border rounded bg-light">
                                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                            <p class="mb-0 fw-bold">No hay productos agregados al remito</p>
                                                            <small class="text-muted">Agregá líneas desde los pedidos pendientes o cargá un producto sin pedido</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- TAB 3: FACTURAR — arma y guarda una factura (Borrador) a partir de las
                                                 líneas pendientes de ESTE remito, sin salir de la pantalla de remitos.
                                                 No usa <form> propio (evita anidar forms dentro de #formVentaRemito);
                                                 sus inputs quedan siempre habilitados aunque el resto del modal esté
                                                 en modo solo lectura — ver cargarRemitoComun() en ventas_remitos.js. -->
                                            <div class="tab-pane fade" id="facturar" role="tabpanel">
                                                <div class="alert alert-light border small" id="facturarRemitoInfo">Cargando datos del remito...</div>
                                                <input type="hidden" id="facturar_venta_remito_id">

                                                <!-- Facturas ya generadas a partir de este remito: se puede confirmar,
                                                     ver o eliminar (según su estado) sin salir de acá. -->
                                                <div class="card card-outline card-primary mb-3" id="cardFacturasDelRemito" style="display: none;">
                                                    <div class="card-header py-1 bg-info bg-opacity-10">
                                                        <h6 class="mb-0 small"><i class="fas fa-file-invoice-dollar me-2"></i>Facturas de este remito</h6>
                                                    </div>
                                                    <div class="card-body py-2" id="facturasDelRemitoContainer"></div>
                                                </div>

                                                <div class="card-header py-1 bg-success bg-opacity-10 rounded mb-2 d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0 small" id="facturarFormTitulo"><i class="fas fa-plus-circle me-2"></i>Nueva Factura Desde Este Remito</h6>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelarEdicionFacturaRemito">
                                                        <i class="fas fa-times me-1"></i>Cancelar edición
                                                    </button>
                                                </div>
                                                <!-- Líneas de la factura en edición que NO vienen de este remito (manuales u
                                                     otro remito): se muestran de referencia y se conservan tal cual al guardar. -->
                                                <div id="facturarLineasFijasContainer"></div>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Punto De Venta *</label>
                                                        <select class="form-select form-select-sm" id="facturar_punto_venta_id">
                                                            <option value="">Seleccionar</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Tipo Comprobante *</label>
                                                        <select class="form-select form-select-sm" id="facturar_comprobante_tipo_id">
                                                            <option value="">Seleccionar PV</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold small">Vencimiento *</label>
                                                        <input type="date" class="form-control form-control-sm" id="facturar_f_vto">
                                                    </div>
                                                </div>
                                                <div id="facturarLineasContainer">
                                                    <div class="text-muted small text-center p-3 border rounded bg-light">Elegí punto de venta para continuar.</div>
                                                </div>
                                                <div id="facturarTotales"></div>
                                                <div class="text-end mt-3">
                                                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnGuardarFacturaDesdeRemito">
                                                        <i class="fas fa-save me-1"></i>Guardar Factura
                                                    </button>
                                                </div>
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
                                            <i class="fas fa-save me-1"></i>Guardar Remito
                                        </button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ESTILOS (mismo criterio visual que ventas_pedidos.php)        -->
    <!-- ============================================================ -->
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        }
        .bg-gradient-primary { background: var(--primary-gradient) !important; }

        .modal-fullscreen .modal-dialog { max-width: 100%; margin: 0; height: 100vh; }
        .modal-fullscreen .modal-content { height: 100vh; border-radius: 0; }
        .modal-fullscreen .modal-body { overflow-y: auto; max-height: calc(100vh - 120px); }

        /* Modal principal más ancho (sin pisar el modo pantalla completa, que ya lo maneja Bootstrap) */
        #modalVentaRemito .modal-dialog.modal-xl:not(.modal-fullscreen) { max-width: 1400px; }

        .card-tabs .nav-tabs { border-bottom: 2px solid #dee2e6; background: #f8f9fa; border-radius: 4px 4px 0 0; }
        .card-tabs .nav-tabs .nav-link {
            border: none; border-radius: 0; padding: 0.6rem 1.2rem; color: #6c757d;
            font-weight: 500; font-size: 0.85rem; transition: all 0.3s ease;
        }
        .card-tabs .nav-tabs .nav-link:hover { background: rgba(0,0,0,0.03); color: #1a73e8; }
        .card-tabs .nav-tabs .nav-link.active { color: #1a73e8; background: transparent; border-bottom: 3px solid #1a73e8; }
        .card-tabs .nav-tabs .nav-link .badge { font-size: 0.7rem; padding: 0.2rem 0.5rem; }

        .form-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #495057; margin-bottom: 0.2rem; }
        .form-control-sm, .form-select-sm { border-radius: 4px; border-color: #ced4da; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
        .form-control-sm:focus, .form-select-sm:focus { border-color: #1a73e8; box-shadow: 0 0 0 0.2rem rgba(26, 115, 232, 0.15); }

        #contenedor-detalles, #contenedor-pendientes, #resultados_busqueda { font-size: 0.85rem; }
        #contenedor-detalles table, #contenedor-pendientes table, #resultados_busqueda table { border-radius: 8px; overflow: hidden; }
        #contenedor-detalles thead th, #contenedor-pendientes thead th, #resultados_busqueda thead th {
            background: #f1f3f5 !important; border-bottom: 2px solid #dee2e6; font-weight: 600;
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; padding: 0.5rem 0.75rem;
        }
        #contenedor-detalles tbody td, #contenedor-pendientes tbody td, #resultados_busqueda tbody td { padding: 0.4rem 0.75rem; vertical-align: middle; }
        #contenedor-detalles tbody tr:hover, #contenedor-pendientes tbody tr:hover, #resultados_busqueda tbody tr:hover { background: #f8f9fa; }
        #contenedor-detalles tbody tr.table-info { background: #e3f2fd !important; }

        .detalles-vacio { color: #6c757d; border-radius: 8px; background: #f8f9fa !important; border: 2px dashed #dee2e6 !important; }

        /* Stepper compacto de cantidad en la tabla de detalle (+/- no se envuelven en columnas angostas) */
        .cantidad-stepper {
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            width: fit-content;
            border: 1px solid #ced4da;
            border-radius: 4px;
            overflow: hidden;
        }
        .cantidad-stepper .btn-stepper {
            flex: 0 0 auto;
            width: 20px;
            height: 24px;
            padding: 0;
            border: none;
            background: #f1f3f5;
            color: #495057;
            font-size: 13px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .cantidad-stepper .btn-stepper:hover { background: #dee2e6; }
        .cantidad-stepper .btn-stepper:active { background: #ced4da; }
        .cantidad-stepper .cantidad-stepper-input {
            flex: 0 0 auto;
            width: 46px;
            height: 24px;
            border: none;
            border-left: 1px solid #ced4da;
            border-right: 1px solid #ced4da;
            text-align: center;
            font-size: 12px;
            padding: 0 2px;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .cantidad-stepper .cantidad-stepper-input:focus {
            outline: none;
            background: #eef6ff;
        }
        .cantidad-stepper .cantidad-stepper-input::-webkit-inner-spin-button,
        .cantidad-stepper .cantidad-stepper-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Buscador de "producto sin pedido" por etiquetas (mismo patrón que el ABM de productos) */
        .tag-item {
            display: inline-flex;
            align-items: center;
            background-color: #e9ecef;
            border-radius: 16px;
            padding: 2px 8px 2px 12px;
            font-size: 13px;
            font-weight: 500;
            color: #212529;
            transition: all 0.2s;
            margin: 2px 2px;
            white-space: nowrap;
            max-width: 200px;
            border: 1px solid #dee2e6;
        }
        .tag-item:hover { background-color: #dee2e6; }
        .tag-item .tag-remove {
            cursor: pointer;
            margin-left: 6px;
            font-size: 14px;
            color: #6c757d;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            line-height: 1;
        }
        .tag-item .tag-remove:hover { color: #dc3545; background-color: rgba(220, 53, 69, 0.1); }
        .tag-item .tag-text { max-width: 150px; overflow: hidden; text-overflow: ellipsis; }

        #busqueda_producto_container {
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            min-height: 38px;
            padding: 4px 8px;
            cursor: text;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        #busqueda_producto_container:focus-within {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        #busqueda_producto {
            background: transparent;
            border: none;
            outline: none;
            padding: 4px 0;
            font-size: 14px;
            min-width: 80px;
            flex: 1;
        }

        .no-spinner::-webkit-inner-spin-button, .no-spinner::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinner { -moz-appearance: textfield; appearance: textfield; }

        .was-validated .form-control:valid, .was-validated .form-select:valid { border-color: #28a745 !important; background-image: none !important; }
        .was-validated .form-control:invalid, .was-validated .form-select:invalid { border-color: #dc3545 !important; background-image: none !important; }
        .invalid-feedback { font-size: 0.7rem; }

        .dt-buttons { display: none !important; }
        #tablaVentasRemitos_length, #tablaVentasRemitos_filter { margin: 0; padding: 0; }
        #tablaVentasRemitos_length label, #tablaVentasRemitos_filter label { display: flex; align-items: center; margin-bottom: 0; gap: 5px; font-size: 0.85rem; }
        #tablaVentasRemitos_length select { width: auto; display: inline-block; margin: 0 5px; }
        #tablaVentasRemitos_filter input { width: 200px; margin-left: 5px; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { display: none !important; }
        .dataTables_paginate .paginate_button { padding: 0.2rem 0.5rem; font-size: 0.8rem; }
        .btn-accion { margin-bottom: 2px; }

        /* Ubicación en "Pedidos pendientes de entrega": mismos colores que el ABM
           de productos (.badge-ubicacion + badge-seccion/estanteria/estante/posicion). */
        .badge-ubicacion { font-size: 0.7rem; padding: 0.2rem 0.4rem; margin-bottom: 0.1rem; border-radius: 0.25rem; }
        .badge-seccion { background-color: #dc3545 !important; color: #ffffff !important; }
        .badge-estanteria { background-color: #ffc107 !important; color: #000000 !important; }
        .badge-estante { background-color: #198754 !important; color: #ffffff !important; }
        .badge-posicion { background-color: #6c757d !important; color: #ffffff !important; }
        .ubicaciones-pendiente-container { display: flex; flex-direction: column; gap: 2px; }

        /* Solapa "Facturar" del modal de remito */
        #facturarLineasContainer table { font-size: 0.85rem; }
        #facturarLineasContainer input[type="number"] { width: 90px; }
        .facturar-totales-box { background: #f8f9fb; border: 1px solid #dee2e6; border-radius: 8px; padding: 0.5rem 0.6rem; margin-top: 0.75rem; display: flex; gap: 0.5rem; }
        .facturar-totales-box .facturar-totales-item { flex: 1; text-align: center; }
        .facturar-totales-box .facturar-totales-item .label { font-size: 0.66rem; text-transform: uppercase; color: #6c757d; display: block; }
        .facturar-totales-box .facturar-totales-item .valor { font-weight: 600; }
        .facturar-totales-box .facturar-totales-final { background: #1a73e8; color: #fff; border-radius: 6px; }
        .facturar-totales-box .facturar-totales-final .label { color: #fff; opacity: .85; }
        .facturar-totales-box .facturar-totales-final .valor { font-size: 1.1rem; }

        @media (max-width: 768px) {
            .card-header .row > div { margin-bottom: 8px; text-align: center !important; }
            .card-header .col-md-2, .card-header .col-md-3, .card-header .col-md-4 { width: 100%; }
            #tablaVentasRemitos_filter label, #tablaVentasRemitos_length label { justify-content: center; }
            #tablaVentasRemitos_filter input { width: 150px; }
            .btn-group { justify-content: center; flex-wrap: wrap; }
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
        const VENTA_REMITO_ID_INICIAL = <?= $venta_remito_id_inicial ?>;
        const MODULO_ID = <?= $modulo_id ?>;
    </script>
    <script src="ventas_remitos.js?v=<?= filemtime(__DIR__.'/ventas_remitos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>