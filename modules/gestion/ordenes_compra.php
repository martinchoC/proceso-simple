<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Órdenes de Compra";
$currentPage = 'ordenes_compra';
$modudo_idx = 2;
$pagina_idx = 51;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Órdenes de compra','fas fa-shopping-cart', '', ['Compras']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <!-- Dashboard Rápido de Estados -->
                        <div class="card mb-3 border-0 shadow-sm" style="background: #f8fafc; border: 1px solid #e2e8f0 !important; border-radius: 10px;">
                            <div class="card-body p-2 p-md-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 fs-7">
                                            <i class="fas fa-chart-pie me-1"></i>Estados
                                        </span>
                                        <span class="small text-muted fw-semibold d-none d-sm-inline">
                                            Haga clic en una tarjeta para filtrar el listado
                                        </span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-secondary d-none" id="btnLimpiarFiltroEstado" style="font-size: 0.75rem; padding: 2px 8px;">
                                        <i class="fas fa-undo me-1"></i>Ver Todas
                                    </button>
                                </div>
                                <div class="row g-2" id="contenedorDashboardEstados">
                                    <div class="col-12 text-center text-muted py-2 small">
                                        <i class="fas fa-spinner fa-spin me-1"></i>Cargando resumen de estados...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div id="contenedor-boton-agregar" class="d-inline"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dataTables_length" id="tablaOrdenesCompra_length"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dataTables_filter" id="tablaOrdenesCompra_filter"></div>
                                            </div>
                                            <div class="col-md-4 text-end">
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
                                        <table id="tablaOrdenesCompra" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="100">Tipo</th>
                                                    <th width="120">Sucursal</th>        <!-- NUEVA -->
                                                    <th width="120">Punto Venta</th> 
                                                    <th width="120">Número</th>
                                                    <th width="200">Proveedor</th>
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

            <!-- Modal principal -->
            <div class="modal fade" id="modalOrdenCompra" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2 d-flex justify-content-between align-items-center" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title mb-0" id="modalLabel">
                                <i class="fas fa-shopping-cart me-2 text-primary"></i>Orden de Compra
                            </h5>
                            <div class="d-flex align-items-center ms-auto gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formOrdenCompra" class="needs-validation" novalidate>
                                <input type="hidden" id="orden_compra_id" name="orden_compra_id" />
                                <input type="hidden" id="producto_iva_id" name="producto_iva_id" />
                                <input type="hidden" id="entidad_id" name="entidad_id" />
                                <input type="hidden" id="entidad_sucursal_id" name="entidad_sucursal_id" />
                                
                                <!-- SECCIÓN ENCABEZADO - Con fondo diferenciado -->
                                <div class="card mb-3 border-primary" style="background-color: #f0f7ff;">
                                    <div class="card-header py-2 bg-primary bg-opacity-10 border-bottom border-primary">
                                        <h6 class="mb-0 text-primary">
                                            <i class="fas fa-file-invoice me-2"></i>Datos de la Orden
                                        </h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <!-- Primera fila de cabecera -->
                                        <div class="row mb-2">
                                            <div class="col-md-2 mb-2">
                                                <label for="sucursal_id" class="form-label small mb-1">Sucursal</label>
                                                <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                                    <option value="">Seleccionar sucursal</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione sucursal</div>
                                            </div>
                                            
                                            <div class="col-md-2 mb-2">
                                                <label for="punto_venta_id" class="form-label small mb-1">Punto de Venta</label>
                                                <select class="form-select form-select-sm" id="punto_venta_id" name="punto_venta_id" required>
                                                    <option value="">Primero seleccione sucursal</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione punto de venta</div>
                                            </div>
                                            
                                            <div class="col-md-3 mb-2">
                                                <label for="entidad_combo" class="form-label small mb-1">Proveedor / Sucursal</label>
                                                <select class="form-select form-select-sm" id="entidad_combo" name="entidad_combo" required>
                                                    <option value="">Seleccionar proveedor o sucursal</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione proveedor o sucursal</div>
                                            </div>

                                            <div class="col-md-2 mb-2">
                                                <label for="tipo_circuito" class="form-label small mb-1 fw-bold text-primary">Modalidad / Circuito</label>
                                                <select class="form-select form-select-sm border-primary" id="tipo_circuito" name="tipo_circuito">
                                                    <option value="BIENES">Bienes / Mercadería</option>
                                                    <option value="CONTRATO_RECURRENTE">Contrato / Servicio Fijo</option>
                                                    <option value="CONTRATO_AVANCE">Contrato c/ Certificación Avance</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-2 mb-2">
                                                <label for="comprobante_tipo_id" class="form-label small mb-1">Tipo</label>
                                                <select class="form-select form-select-sm" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                                    <option value="">Seleccionar</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione el tipo</div>
                                            </div>
                                            
                                            <div class="col-md-1 mb-2">
                                                <label for="comprobante_nro" class="form-label small mb-1">Número <?= ui_ayuda('Se asigna al confirmar') ?></label>
                                                <input type="number" class="form-control form-control-sm" id="comprobante_nro" name="comprobante_nro" value="0" min="1" readonly>
                                                <div class="invalid-feedback small">Se asigna al confirmar</div>
                                            </div>
                                        </div>

                                        <!-- SECCIÓN PARÁMETROS DE CONTRATO Y VIGENCIA -->
                                        <div id="seccionContrato" class="card mb-2 border-info shadow-sm" style="display: none; background-color: #f7fcfe;">
                                            <div class="card-header py-1 bg-info bg-opacity-10 border-bottom border-info d-flex justify-content-between align-items-center">
                                                <span class="small fw-bold text-info"><i class="fas fa-file-contract me-1"></i>Condiciones y Vigencia del Contrato</span>
                                                <span class="badge bg-info text-dark" id="badgeModalCircuito">Contrato</span>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row g-2">
                                                    <div class="col-md-3">
                                                        <label for="contrato_nro_legal" class="form-label small mb-0">N° Legal / Instrumento</label>
                                                        <input type="text" class="form-control form-control-sm" id="contrato_nro_legal" name="contrato_nro_legal" placeholder="Ej: CONTR-2026-004">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="fecha_inicio" class="form-label small mb-0">Inicio Vigencia</label>
                                                        <input type="date" class="form-control form-control-sm" id="fecha_inicio" name="fecha_inicio">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="fecha_fin" class="form-label small mb-0">Fin Vigencia (Vto.)</label>
                                                        <input type="date" class="form-control form-control-sm" id="fecha_fin" name="fecha_fin">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="dias_preaviso_rescision" class="form-label small mb-0">Preaviso (días)</label>
                                                        <input type="number" class="form-control form-control-sm" id="dias_preaviso_rescision" name="dias_preaviso_rescision" value="30" min="0">
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-center pt-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="tiene_renovacion_automatica" name="tiene_renovacion_automatica" value="1">
                                                            <label class="form-check-label small" for="tiene_renovacion_automatica">
                                                                Renovación automática
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Parámetros específicos para Contrato de Avance -->
                                                <div id="seccionAvanceParams" class="row g-2 mt-2 pt-2 border-top border-info border-opacity-25" style="display: none;">
                                                    <div class="col-md-3">
                                                        <label for="pct_anticipo_financiero" class="form-label small mb-0">% Anticipo a amortizar</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="pct_anticipo_financiero" name="pct_anticipo_financiero" value="0.00">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="pct_fondo_reparo" class="form-label small mb-0">% Fondo de reparo / retención</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="pct_fondo_reparo" name="pct_fondo_reparo" value="0.00">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="responsable_tecnico_id" class="form-label small mb-0">Inspector / Responsable Técnico</label>
                                                        <select class="form-select form-select-sm" id="responsable_tecnico_id" name="responsable_tecnico_id">
                                                            <option value="">Seleccionar inspector asignado</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <!-- Segunda fila de cabecera -->
                                        <div class="row mb-2">
                                            <div class="col-md-2 mb-2">
                                                <label for="f_emision" class="form-label small mb-1">Emisión</label>
                                                <input type="date" class="form-control form-control-sm" id="f_emision" name="f_emision" required>
                                                <div class="invalid-feedback small">Fecha obligatoria</div>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label for="f_entrega_estimada" class="form-label small mb-1">Entrega Est.</label>
                                                <input type="date" class="form-control form-control-sm" id="f_entrega_estimada" name="f_entrega_estimada" min="">
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label for="moneda_id" class="form-label small mb-1">Moneda</label>
                                                <select class="form-select form-select-sm" id="moneda_id" name="moneda_id" required>
                                                    <option value="">Seleccionar</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione moneda</div>
                                            </div>
                                            <div class="col-md-1 mb-2">
                                                <label for="tipo_cambio" class="form-label small mb-1">TC</label>
                                                <input type="number" class="form-control form-control-sm no-spinner" id="tipo_cambio" name="tipo_cambio">
                                            </div>
                                            <div class="col-md-4 mb-2">
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
                                    </div>
                                </div>

                                <!-- BOTONES SUPERIORES (Guardar/Cancelar) -->
                                <div class="row mt-2 mb-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                                            <i class="fas fa-save me-1"></i>Guardar Orden
                                        </button>
                                    </div>
                                </div>

                                <!-- Totales en formato horizontal -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="card bg-success bg-opacity-10 border-success">
                                            <div class="card-body py-2">
                                                <div class="row align-items-center">
                                                    <div class="col-md-3">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="small text-success fw-bold">Total Neto:</span>
                                                            <span class="fw-bold" id="total_neto_display">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="small text-secondary">No Gravado:</span>
                                                            <span id="no_gravado_display">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="small text-secondary">Exento:</span>
                                                            <span id="exento_display">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="small text-warning">Impuestos:</span>
                                                            <span id="impuestos_display">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="d-flex justify-content-between fw-bold text-primary border-start ps-2">
                                                            <span class="small">TOTAL:</span>
                                                            <span id="total_display" class="text-primary">0.00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="total_neto" name="total_neto" value="0">
                                        <input type="hidden" id="no_gravado" name="no_gravado" value="0">
                                        <input type="hidden" id="exento" name="exento" value="0">
                                        <input type="hidden" id="impuestos" name="impuestos" value="0">
                                        <input type="hidden" id="total" name="total" value="0">
                                    </div>
                                </div>

                                <!-- SECCIÓN PRODUCTOS - Con estilo diferenciado -->
                                <div class="card border-warning">
                                    <div class="card-header py-2 bg-warning bg-opacity-10 border-bottom border-warning">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 text-warning">
                                                <i class="fas fa-boxes me-2"></i>Detalle de Productos
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-outline-warning" id="btnNuevoProductoRapido">
                                                <i class="fas fa-bolt me-1"></i>Nuevo Producto Rápido
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-2">
                                        <!-- Fila de agregado rápido de productos -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="card card-primary card-outline border-info">
                                                    <div class="card-header py-1 bg-info bg-opacity-10">
                                                        <h6 class="mb-0 small text-info"><i class="fas fa-plus-circle me-2"></i>Agregar Producto</h6>
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
                                                <div id="contenedor-detalles" class="table-responsive">
                                                    <!-- Los detalles se cargarán dinámicamente aquí -->
                                                    <div class="detalles-vacio text-center p-4 border rounded bg-light" id="detalles-vacio">
                                                        <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                                                        <p class="mb-0">No hay productos agregados</p>
                                                        <small class="text-muted">Seleccione un producto para comenzar</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para nuevo producto rápido -->
            <div class="modal fade" id="modalNuevoProductoRapido" tabindex="-1" aria-labelledby="modalProductoRapidoLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2 d-flex justify-content-between align-items-center bg-info bg-opacity-10">
                            <h5 class="modal-title mb-0" id="modalProductoRapidoLabel">
                                <i class="fas fa-bolt me-2 text-info"></i>Nuevo Producto Rápido
                            </h5>
                            <button type="button" class="btn-close m-0 ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
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

            <!-- Modal de Seguimiento de Contratos y Certificaciones de Avance -->
            <div class="modal fade" id="modalCertificaciones" tabindex="-1" aria-labelledby="modalCertificacionesLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-light border-bottom">
                            <h5 class="modal-title mb-0" id="modalCertificacionesLabel">
                                <i class="fas fa-clipboard-check me-2 text-primary"></i>Seguimiento de Contrato y Certificaciones de Avance
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <!-- Banner Resumen del Contrato -->
                            <div class="card mb-3 border-0 shadow-sm" style="background-color: #f8fafd; border-left: 4px solid #0d6efd !important;">
                                <div class="card-body p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <h5 class="mb-1 fw-bold text-dark" id="cert_header_titulo">Contrato N° ...</h5>
                                            <div class="text-muted small">
                                                <span class="me-3"><i class="fas fa-building me-1"></i><strong id="cert_header_proveedor">...</strong></span>
                                                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i>Vigencia: <span id="cert_header_vigencia">...</span></span>
                                                <span id="cert_header_badge_vigencia"></span>
                                            </div>
                                        </div>
                                        <div class="col-md-5 text-md-end mt-2 mt-md-0">
                                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaCertificacionModal">
                                                <i class="fas fa-plus me-1"></i>Nueva Medición / Certificado
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Métricas y Barras de Progreso -->
                                    <div class="row mt-3 g-2 text-center">
                                        <div class="col-md-3 col-6">
                                            <div class="p-2 border rounded bg-white">
                                                <div class="text-muted small">Monto Total Contratado</div>
                                                <h6 class="mb-0 fw-bold text-dark" id="cert_kpi_total">$0.00</h6>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="p-2 border rounded bg-white">
                                                <div class="text-muted small">Bruto Certificado</div>
                                                <h6 class="mb-0 fw-bold" style="color: #6f42c1;" id="cert_kpi_certificado">$0.00</h6>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="p-2 border rounded bg-white">
                                                <div class="text-muted small">Facturado Acumulado</div>
                                                <h6 class="mb-0 fw-bold text-primary" id="cert_kpi_facturado">$0.00</h6>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="p-2 border rounded bg-white">
                                                <div class="text-muted small">Saldo por Ejecutar</div>
                                                <h6 class="mb-0 fw-bold text-success" id="cert_kpi_saldo">$0.00</h6>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-semibold">Avance Físico Certificado</span>
                                                <span class="fw-bold" id="cert_bar_fisico_txt">0%</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar" role="progressbar" id="cert_bar_fisico" style="width: 0%; background-color: #6f42c1;"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-semibold">Avance Financiero Facturado</span>
                                                <span class="fw-bold" id="cert_bar_facturado_txt">0%</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-primary" role="progressbar" id="cert_bar_facturado" style="width: 0%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Listado de Certificados -->
                            <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-list me-1"></i>Historial de Certificaciones de Avance</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-bordered align-middle" id="tablaCertificacionesListado" style="width: 100%;">
                                    <thead class="table-light small">
                                        <tr>
                                            <th class="text-center" width="50">N°</th>
                                            <th class="text-center" width="90">Fecha Medición</th>
                                            <th class="text-center" width="80">Período</th>
                                            <th class="text-center" width="70">% Acum.</th>
                                            <th class="text-center" width="70">% Período</th>
                                            <th class="text-end" width="110">Bruto Período</th>
                                            <th class="text-end" width="100">Amort. Anticipo</th>
                                            <th class="text-end" width="100">Fondo Reparo</th>
                                            <th class="text-end" width="120">Neto Facturable</th>
                                            <th class="text-center" width="100">Estado</th>
                                            <th class="text-center" width="100">Factura</th>
                                            <th class="text-center" width="90">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small" id="tbodyCertificaciones">
                                        <!-- Rellenado por JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submodal para Crear/Editar Medición de Certificación -->
            <div class="modal fade" id="modalNuevaCertificacion" tabindex="-1" aria-labelledby="modalNuevaCertLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-primary bg-opacity-10 border-bottom border-primary">
                            <h6 class="modal-title mb-0 text-primary" id="modalNuevaCertLabel">
                                <i class="fas fa-ruler-combined me-2"></i>Registrar Certificación de Avance
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formNuevaCertificacion" class="needs-validation" novalidate>
                                <input type="hidden" id="cert_orden_compra_id" name="orden_compra_id" value="">
                                <input type="hidden" id="cert_certificacion_id" name="certificacion_id" value="">

                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <label for="cert_fecha_medicion" class="form-label small mb-1">Fecha de Medición *</label>
                                        <input type="date" class="form-control form-control-sm" id="cert_fecha_medicion" name="fecha_medicion" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cert_periodo_mes" class="form-label small mb-1">Período (AAAA-MM)</label>
                                        <input type="text" class="form-control form-control-sm" id="cert_periodo_mes" name="periodo_mes" placeholder="2026-11" maxlength="7">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cert_avance_acumulado_pct" class="form-label small mb-1">% Avance Físico Acumulado *</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="cert_avance_acumulado_pct" name="avance_acumulado_pct" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cuadro de Cálculo Financiero Automático -->
                                <div class="card mb-3 border-light bg-light">
                                    <div class="card-body p-2 small">
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <span class="text-muted d-block">% Período</span>
                                                <strong id="calc_cert_pct_periodo">0%</strong>
                                            </div>
                                            <div class="col-3">
                                                <span class="text-muted d-block">Bruto Período</span>
                                                <strong id="calc_cert_bruto">$0.00</strong>
                                            </div>
                                            <div class="col-3 text-danger">
                                                <span class="d-block">Deducciones (Ant.+Rep.)</span>
                                                <strong id="calc_cert_deducciones">-$0.00</strong>
                                            </div>
                                            <div class="col-3 text-success">
                                                <span class="d-block">Neto Facturable</span>
                                                <strong class="fs-6" id="calc_cert_neto">$0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label for="cert_observaciones" class="form-label small mb-1">Observaciones / Memoria Técnica</label>
                                    <textarea class="form-control form-control-sm" id="cert_observaciones" name="observaciones_tecnicas" rows="2" placeholder="Detalle de trabajos ejecutados en el período..."></textarea>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="cert_enviar_revision" name="enviar_a_revision" value="1" checked>
                                    <label class="form-check-label small" for="cert_enviar_revision">
                                        Enviar directamente a Revisión Técnica (estado Pendiente de Aprobación)
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardarCertificacion">
                                <i class="fas fa-save me-1"></i>Guardar Certificación
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

        /* Asegurar que el botón de cerrar siempre sea visible y arriba a la derecha */
        .modal-header .btn-close {
            background-color: rgba(0,0,0,0.06);
            border-radius: 50%;
            padding: 0.5rem;
            margin: 0;
            transition: background-color 0.2s ease;
        }

        .modal-header .btn-close:hover {
            background-color: rgba(0,0,0,0.15);
        }

        /* Tarjetas de filtro rápido de estados */
        .card-kpi-estado {
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: 2px solid transparent !important;
            background: #ffffff;
            border-radius: 8px;
            user-select: none;
        }
        .card-kpi-estado:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #cbd5e1 !important;
        }
        .card-kpi-estado.active {
            border-color: #0d6efd !important;
            background: #f0f7ff !important;
            box-shadow: 0 4px 14px rgba(13, 110, 253, 0.18);
        }
        .card-kpi-estado .kpi-count {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .card-kpi-estado .kpi-monto {
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
    <!-- En el head, después de los estilos existentes, agregar: -->
<style>
    /* Estilos para los filtros de columna */
    .column-filter {
        width: 100%;
        padding: 0.25rem;
        font-size: 0.8rem;
        border: 1px solid #ced4da;
        border-radius: 0.2rem;
        margin-top: 0.25rem;
    }
    
    .column-filter:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    
    /* Ajustes para la tabla */
    #tablaOrdenesCompra thead th {
        vertical-align: top;
        padding-bottom: 0.5rem;
    }
    
    #tablaOrdenesCompra thead .filter-row {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
    
    /* Estilo para el botón de imprimir en edición */
    .btn-imprimir-editar {
        margin-left: 0.5rem;
    }
    /* Asegurar que el texto de estado sea negro cuando no tiene badge */
    #tablaOrdenesCompra tbody td .text-dark {
        color: #212529 !important;
        font-weight: normal;
    }

    /* Opcional: quitar cualquier estilo heredado */
    #tablaOrdenesCompra tbody td span:not(.badge) {
        color: #212529;
    }
    /* ===== ESTILOS RESPONSIVE ===== */
    @media (max-width: 768px) {
        /* Ajustes generales */
        .card-header .float-end {
            float: none !important;
            margin-top: 10px;
            text-align: right;
        }
        
        #contenedor-boton-agregar {
            display: block !important;
            margin-bottom: 10px;
        }
        
        /* Ajustes de la tabla */
        .dataTables_wrapper {
            overflow-x: auto;
        }
        
        #tablaOrdenesCompra {
            width: 100% !important;
            font-size: 12px;
        }
        
        #tablaOrdenesCompra th, 
        #tablaOrdenesCompra td {
            white-space: nowrap;
            padding: 0.5rem 0.3rem;
        }
        
        /* Ocultar columnas menos importantes en móvil */
        #tablaOrdenesCompra th:nth-child(3),
        #tablaOrdenesCompra td:nth-child(3),
        #tablaOrdenesCompra th:nth-child(4),
        #tablaOrdenesCompra td:nth-child(4),
        #tablaOrdenesCompra th:nth-child(7),
        #tablaOrdenesCompra td:nth-child(7) {
            display: none;
        }
        
        /* Ajustes del modal */
        .modal-dialog {
            margin: 0;
            max-width: 100%;
            height: 100%;
        }
        
        .modal-content {
            border-radius: 0;
            height: 100%;
        }
        
        .modal-body {
            padding: 0.75rem;
            overflow-y: auto;
            max-height: calc(100vh - 60px);
        }
        
        /* Ajustes de filas del formulario */
        .row.mb-2 > [class*="col-"] {
            margin-bottom: 10px;
        }
        
        /* Ajustes de la sección de agregar producto */
        .card-body .row.g-2 {
            flex-direction: column;
        }
        
        .card-body .row.g-2 > [class*="col-"] {
            width: 100%;
            margin-bottom: 8px;
        }
        
        #resultados_busqueda {
            position: static !important;
            max-height: 150px;
            margin-top: 5px;
        }
        
        /* Ajustes de la tabla de detalles */
        #contenedor-detalles {
            overflow-x: auto;
        }
        
        #contenedor-detalles table {
            font-size: 11px;
        }
        
        #contenedor-detalles table th,
        #contenedor-detalles table td {
            white-space: nowrap;
            padding: 0.3rem 0.2rem;
        }
        
        /* Ocultar columnas menos importantes en móvil */
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
        
        /* Ajustes de los totales */
        .card-body .row.align-items-center {
            flex-direction: column;
        }
        
        .card-body .row.align-items-center > [class*="col-"] {
            width: 100%;
            margin-bottom: 5px;
        }
        
        .border-start {
            border-left: none !important;
            padding-left: 0 !important;
        }
        
        /* Ajustes de botones */
        .btn-group {
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }
        
        /* Ajustes de filtros de columna */
        .column-filter {
            font-size: 0.7rem;
            padding: 0.15rem;
        }
    }

    @media (max-width: 480px) {
        /* Ocultar más columnas en móviles muy pequeños */
        #tablaOrdenesCompra th:nth-child(6),
        #tablaOrdenesCompra td:nth-child(6) {
            display: none;
        }
        
        #contenedor-detalles table th:nth-child(8),
        #contenedor-detalles table td:nth-child(8) {
            display: none;
        }
        
        /* Ajustes de títulos */
        h3.mb-0 {
            font-size: 1.2rem;
        }
        
        /* Botones más pequeños */
        .btn-sm {
            padding: 0.15rem 0.3rem;
            font-size: 0.65rem;
        }
    }

    /* Mejoras para tablets */
    @media (min-width: 769px) and (max-width: 1024px) {
        #tablaOrdenesCompra {
            font-size: 13px;
        }
        
        #tablaOrdenesCompra th, 
        #tablaOrdenesCompra td {
            padding: 0.4rem 0.25rem;
        }
        
        .modal-dialog {
            max-width: 95%;
            margin: 1.75rem auto;
        }
    }

    /* Estilos para el scroll horizontal en tablas */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Ajustes para los inputs en móvil */
    input, select, textarea {
        font-size: 16px !important; /* Evita zoom automático en iOS */
    }

    /* Ajustes para los botones de acción en móvil */
    .btn-accion {
        margin-bottom: 2px;
    }

    /* Estilo para el contenedor de exportación en móvil */
    .dataTables_wrapper .col-md-6 {
        text-align: center !important;
        margin-bottom: 10px;
    }

    /* Ajustes para el paginador en móvil */
    .dataTables_paginate {
        white-space: nowrap;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .dataTables_paginate .paginate_button {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
    /* Ocultar los botones que DataTable intenta agregar */
    .dt-buttons {
        display: none !important;
    }

    /* Estilos para los controles en el header */
    #tablaOrdenesCompra_length,
    #tablaOrdenesCompra_filter {
        margin: 0;
        padding: 0;
    }

    #tablaOrdenesCompra_length label,
    #tablaOrdenesCompra_filter label {
        display: flex;
        align-items: center;
        margin-bottom: 0;
        gap: 5px;
    }

    #tablaOrdenesCompra_length select {
        width: auto;
        display: inline-block;
        margin: 0 5px;
    }

    #tablaOrdenesCompra_filter input {
        width: 200px;
        margin-left: 5px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .card-header .row > div {
            margin-bottom: 10px;
            text-align: center !important;
        }
        
        .card-header .col-md-3,
        .card-header .col-md-2,
        .card-header .col-md-4 {
            width: 100%;
        }
        
        #tablaOrdenesCompra_filter label,
        #tablaOrdenesCompra_length label {
            justify-content: center;
        }
        
        #tablaOrdenesCompra_filter input {
            width: 150px;
        }
        
        .btn-group {
            justify-content: center;
        }
    }

    /* Ocultar los controles originales de DataTable */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        display: none !important;
    }
}
</style>

    <link rel="stylesheet" type="text/css"
        href="<?= asset_local('css/datatables-buttons-2.4.1/buttons.dataTables.min.css') ?>">

    <script src="<?= asset_local('js/datatables-buttons-2.4.1/dataTables.buttons.min.js') ?>"></script>
    <script src="<?= asset_local('js/jszip-3.10.1/jszip.min.js') ?>"></script>
    <script src="<?= asset_local('js/pdfmake-0.1.53/pdfmake.min.js') ?>"></script>
    <script src="<?= asset_local('js/pdfmake-0.1.53/vfs_fonts.js') ?>"></script>
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.html5.min.js') ?>"></script>
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    
    <script src="ordenes_compra.js?v=<?= filemtime(__DIR__.'/ordenes_compra.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>