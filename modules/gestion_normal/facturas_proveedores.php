<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Facturas de Proveedores";
$currentPage = 'facturas_proveedores';

$modudo_idx = 5;
$pagina_idx = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 557;
$ui_simple = true;
$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 5;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
// "Cargar desde archivo": mismo permiso que "Agregar" ($pagina_idx, como en facturas_proveedores_ajax.php)
$puede_cargar_archivo = usuario_puede_funcion($conexion, intval($usuario_logueado_id), intval($current_empresa_id), $pagina_idx, 'agregar');

// Estados posibles de la factura de proveedor (según transiciones configuradas para la página)
$estados_filtro = [];
$sql_estados_fp = "SELECT er.estado_registro_id, er.estado_registro, er.codigo_estandar
                   FROM conf__estados_registros er
                   WHERE er.estado_registro_id IN (
                       SELECT tabla_estado_registro_origen_id FROM conf__paginas_funciones WHERE pagina_id = ? AND tabla_estado_registro_id = 1 AND tabla_estado_registro_origen_id > 0
                       UNION
                       SELECT tabla_estado_registro_destino_id FROM conf__paginas_funciones WHERE pagina_id = ? AND tabla_estado_registro_id = 1 AND tabla_estado_registro_destino_id > 0
                   )
                   ORDER BY er.orden_estandar ASC, er.estado_registro_id ASC";
if ($stmt_est_fp = mysqli_prepare($conexion, $sql_estados_fp)) {
    mysqli_stmt_bind_param($stmt_est_fp, "ii", $pagina_idx, $pagina_idx);
    mysqli_stmt_execute($stmt_est_fp);
    $res_est_fp = mysqli_stmt_get_result($stmt_est_fp);
    while ($r_est = mysqli_fetch_assoc($res_est_fp)) {
        $estados_filtro[] = $r_est;
    }
    mysqli_stmt_close($stmt_est_fp);
}
?>

<main class="app-main">
    <?php ui_encabezado('Facturas de proveedores','fas fa-file-invoice', '', ['Compras']); ?>

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
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btnImportarArca" title="Importar Comprobantes Recibidos de ARCA">
                                            <i class="fas fa-file-import me-1"></i> Importar ARCA
                                        </button>
                                        <?php if ($puede_cargar_archivo): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btnCargarArchivo" title="Cargar una factura desde el PDF o la foto del comprobante">
                                            <i class="fas fa-file-upload me-1"></i> Cargar desde archivo
                                        </button>
                                        <?php endif; ?>
                                        <div class="d-inline-flex align-items-center ms-2" style="vertical-align: middle;">
                                            <label for="filtroEstado" class="small text-muted me-1 mb-0">Estado:</label>
                                            <select class="form-select form-select-sm" id="filtroEstado" style="width: auto; min-width: 195px;" title="Filtrar facturas por estado">
                                                <option value="" selected>Todos (excepto Eliminado)</option>
                                                <option value="todos">Todos (incluye Eliminado)</option>
                                                <?php foreach ($estados_filtro as $est): ?>
                                                <option value="<?= intval($est['estado_registro_id']) ?>"><?= htmlspecialchars($est['estado_registro']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
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
                                                    <th width="120" class="text-center">Asiento</th>
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
                <div class="modal-dialog modal-dialog-centered modal-factura-proveedor">
                    <div class="modal-content">
                        <div class="modal-header py-2 d-flex justify-content-between align-items-center" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title mb-0" id="modalLabel">
                                <i class="fas fa-file-invoice me-2 text-primary"></i>Factura de Proveedor
                            </h5>
                            <div class="d-flex align-items-center ms-auto gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                          <div class="fl-split">
                            <!-- Documento leído (carga desde archivo): visor + lo que se leyó; lo llena facturas_proveedores_lectura.js -->
                            <aside id="panelLecturaArchivo" class="fl-panel d-none" aria-label="Documento leído">
                                <div class="fl-panel-barra">
                                    <span class="fw-medium"><i class="fas fa-file-alt me-1"></i>Documento</span>
                                    <span id="flCapas" class="d-inline-flex flex-wrap gap-1"></span>
                                    <span class="ms-auto d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnFlReintentarIa" title="Volver a leer el archivo con IA">
                                            <i class="fas fa-magic me-1"></i>Reintentar con IA
                                        </button>
                                        <a class="btn btn-sm btn-outline-secondary" id="btnFlAbrirArchivo" href="#" target="_blank" rel="noopener" title="Abrir el archivo en otra pestaña">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFlOcultarPanel" title="Ocultar el documento">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                                </div>
                                <div id="flVisor" class="fl-visor"></div>
                                <div id="flResumen" class="fl-resumen"></div>
                            </aside>
                            <div class="fl-form">
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
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="asiento-tab" data-bs-toggle="tab" data-bs-target="#asiento" type="button" role="tab">
                                            <i class="fas fa-book me-1"></i>Asiento Contable
                                            <span class="badge bg-success ms-1 d-none" id="badgeAsientoProv"></span>
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
                                                    <div class="col-md-12 mt-2 d-none" id="asociado_wrap">
                                                        <label class="form-label small mb-1">Comprobante asociado <span class="text-muted">(nota de crédito / débito)</span></label>
                                                        <select class="form-select form-select-sm" id="comprobante_asociado_id">
                                                            <option value="">Sin comprobante asociado</option>
                                                        </select>
                                                        <?= ui_ayuda('Si el comprobante asociado tiene saldo, la nota de crédito se le aplica al confirmarla (con una OP en $0).') ?>
                                                    </div>
                                                    <div class="col-md-6 mt-2 d-none" id="debito_wrap">
                                                        <label class="form-label small mb-1">Debitado de la cuenta <span class="text-muted" id="debito_ayuda">(opcional: débito automático)</span></label>
                                                        <select class="form-select form-select-sm" id="tesoreria_cuenta_debito_id">
                                                            <option value="">Sin débito en cuenta (se paga con OP)</option>
                                                        </select>
                                                        <?= ui_ayuda('Al confirmar se cancela solo con una OP automática que egresa de esa cuenta bancaria.') ?>
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

                                                <!-- Fila Modalidad Descuento y Costo -->
                                                <div class="row mb-2">
                                                    <div class="col-md-5">
                                                        <label class="form-label small mb-1">Modalidad de Descuento</label>
                                                        <select class="form-select form-select-sm" id="modalidad_descuento_factura">
                                                            <option value="EN_FACTURA_GLOBAL">En Factura - Descuento Global (al pie)</option>
                                                            <option value="EN_FACTURA_ITEM">En Factura - Aplicado individualmente por producto</option>
                                                            <option value="NOTA_CREDITO">Con Nota de Crédito posterior</option>
                                                            <option value="SIN_DESCUENTO">Sin Descuento</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small mb-1">¿Costo contempla descuento?</label>
                                                        <select class="form-select form-select-sm" id="precio_compra_contempla_descuento">
                                                            <option value="1">Sí (Costo neto con descuento)</option>
                                                            <option value="0">No (Costo a precio de lista / sin dto)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3" id="col_descuento_nc" style="display:none;">
                                                        <label class="form-label small mb-1">Dto. NC Posterior %</label>
                                                        <input type="number" class="form-control form-control-sm no-spinner" id="descuento_nc_pct" step="0.01" min="0" max="100" value="0">
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
                                                <h6 class="mb-0 text-warning"><i class="fas fa-boxes me-2"></i>Detalle de Productos
                                                    <span id="oc_vinculada_badge" class="badge bg-primary ms-2 d-none">
                                                        <i class="fas fa-link me-1"></i><span id="oc_vinculada_texto"></span>
                                                        <a href="#" class="text-white ms-1" id="btnDesvincularOC" title="Desvincular la orden de compra"><i class="fas fa-times"></i></a>
                                                    </span>
                                                    <span id="cert_vinculada_badge" class="badge bg-success ms-2 d-none">
                                                        <i class="fas fa-award me-1"></i><span id="cert_vinculada_texto"></span>
                                                        <a href="#" class="text-white ms-1" id="btnDesvincularCert" title="Desvincular la certificación"><i class="fas fa-times"></i></a>
                                                    </span>
                                                </h6>
                                                <input type="hidden" id="orden_compra_id" name="orden_compra_id" value="">
                                                <input type="hidden" id="certificacion_id" name="certificacion_id" value="">
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnImportarCertificacion" title="Importar certificación de avance aprobada">
                                                        <i class="fas fa-award me-1"></i>Certificación
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportarOC">
                                                        <i class="fas fa-file-import me-1"></i>Importar OC
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" id="btnNuevoProductoRapido">
                                                        <i class="fas fa-bolt me-1"></i>Nuevo Producto Rápido
                                                    </button>
                                                </div>
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
                                                                <div class="input-group input-group-sm">
                                                                    <input type="text" class="form-control" id="busqueda_producto" placeholder="Buscar producto o servicio..." autocomplete="off">
                                                                    <button class="btn btn-outline-secondary" type="button" id="btnModalSelectorProductos" title="Ver catálogo y seleccionar">
                                                                        <i class="fas fa-ellipsis-h"></i>
                                                                    </button>
                                                                </div>
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
                                    <!-- TAB 4: ASIENTO CONTABLE -->
                                    <div class="tab-pane fade" id="asiento" role="tabpanel">
                                        <div class="card border-primary">
                                            <div class="card-header py-2 bg-primary bg-opacity-10">
                                                <h6 class="mb-0 text-primary"><i class="fas fa-book me-2"></i>Asiento Contable</h6>
                                            </div>
                                            <div class="card-body p-2">
                                                <div id="asientoProvVacio" class="text-center p-4 border rounded bg-light">
                                                    <i class="fas fa-book-open fa-2x text-muted mb-2"></i>
                                                    <p class="mb-0">La factura todavía no tiene asiento contable</p>
                                                    <small class="text-muted">Se genera automáticamente al <strong>confirmarla</strong>.</small>
                                                </div>
                                                <div id="asientoProvDetalle" style="display: none;">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                                        <div>
                                                            <span class="fw-bold me-2">Asiento #<span id="asientoProvId"></span></span>
                                                            <span class="badge bg-primary" id="asientoProvTipo"></span>
                                                            <span class="badge bg-secondary ms-1" id="asientoProvEstado"></span>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted me-3">Fecha: <strong id="asientoProvFecha"></strong></small>
                                                            <span id="asientoProvBalance"></span>
                                                        </div>
                                                    </div>
                                                    <div class="small text-muted fst-italic mb-2" id="asientoProvDescripcion"></div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0" id="tablaAsientoProv">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th style="width: 120px;">Cuenta</th>
                                                                    <th>Nombre</th>
                                                                    <th class="text-end" style="width: 140px;">Debe</th>
                                                                    <th class="text-end" style="width: 140px;">Haber</th>
                                                                    <th>Detalle</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody></tbody>
                                                            <tfoot class="table-light fw-bold">
                                                                <tr>
                                                                    <td colspan="2" class="text-end">Totales</td>
                                                                    <td class="text-end" id="asientoProvTotalDebe"></td>
                                                                    <td class="text-end" id="asientoProvTotalHaber"></td>
                                                                    <td></td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="row mt-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" id="btnCancelarFactura" data-bs-dismiss="modal">
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
                </div>
            </div>

            <!-- Modal Cargar desde archivo: se sube el PDF o la foto, se lee y se abre el formulario pre-cargado -->
            <div class="modal fade" id="modalLecturaArchivo" tabindex="-1" aria-labelledby="modalLecturaArchivoLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h5 class="modal-title mb-0" id="modalLecturaArchivoLabel">
                                <i class="fas fa-file-upload me-2 text-primary"></i>Cargar factura desde archivo
                                <?= ui_ayuda('El archivo (PDF o imagen) se lee directamente con IA. Los datos quedan en el formulario para que los revise; la factura se guarda en Borrador.') ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="flElegir">
                                <label id="flZona" class="fl-zona" for="flArchivo">
                                    <input type="file" id="flArchivo" class="d-none" multiple accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
                                    <span class="fw-medium">Arrastre uno o varios archivos (o una carpeta), o haga clic para elegirlos</span>
                                    <small class="text-muted">PDF, JPG o PNG, hasta 10 MB cada uno</small>
                                </label>
                                <div class="text-center mt-2">
                                    <label class="btn btn-sm btn-outline-secondary mb-0" for="flCarpeta">
                                        <i class="fas fa-folder-open me-1"></i>Elegir una carpeta
                                    </label>
                                    <input type="file" id="flCarpeta" class="d-none" webkitdirectory directory multiple>
                                </div>
                            </div>
                            <!-- Varios archivos: se leen de a uno y se listan; los listos se guardan juntos en Borrador -->
                            <div id="flLote" class="d-none">
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                    <span id="flLoteResumen" class="fw-medium"></span>
                                    <span class="ms-auto d-inline-flex flex-wrap gap-2 align-items-center">
                                        <label for="flLoteSucursal" class="small text-muted mb-0">Sucursal</label>
                                        <select id="flLoteSucursal" class="form-select form-select-sm w-auto"></select>
                                        <button type="button" class="btn btn-sm btn-primary" id="btnFlGuardarListos" disabled>
                                            <i class="fas fa-save me-1"></i>Guardar los listos en Borrador
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFlNuevoLote">Cargar otros</button>
                                    </span>
                                </div>
                                <div class="table-responsive fl-lote-tabla">
                                    <table class="table table-sm align-middle mb-0" data-ui-acciones="no">
                                        <thead><tr><th>Archivo</th><th>Comprobante</th><th>Proveedor</th><th class="text-end">Total</th><th>Estado</th><th></th></tr></thead>
                                        <tbody id="flLoteFilas"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="form-check mt-3 d-none">
                                <input class="form-check-input" type="checkbox" id="flForzarIa" checked>
                                <label class="form-check-label" for="flForzarIa">Leer con IA</label>
                            </div>
                            <div class="small text-muted mt-3" id="flAvisoPrivacidad">
                                <i class="fas fa-magic me-1"></i>El documento se procesa directamente con IA (Google Gemini / Anthropic) según la configuración de la empresa en Configuración &gt; Empresas.
                            </div>
                            <div id="flProgreso" class="fl-progreso d-none" aria-live="polite">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                    <span id="flProgresoTexto">Leyendo…</span>
                                </div>
                                <div class="progress mt-2" style="height:4px;"><div class="progress-bar" id="flProgresoBarra" style="width:0%"></div></div>
                            </div>
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

            <!-- Modal Importar Orden de Compra -->
            <div class="modal fade" id="modalImportarOC" tabindex="-1" aria-labelledby="modalImportarOCLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header py-2 d-flex justify-content-between align-items-center bg-primary bg-opacity-10">
                            <h5 class="modal-title mb-0" id="modalImportarOCLabel">
                                <i class="fas fa-file-import me-2"></i>Importar Orden de Compra
                            </h5>
                            <button type="button" class="btn-close m-0 ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <!-- Paso 1: órdenes del proveedor con pendientes -->
                            <div id="oc_paso_ordenes">
                                <p class="small text-muted mb-2">Órdenes de compra del proveedor con mercadería pendiente de recibir.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>N°</th><th>Emisión</th><th>Entrega est.</th><th>Sucursal</th>
                                                <th>Estado</th><th class="text-center">Líneas pend.</th><th class="text-end">Total</th><th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="oc_tabla_ordenes"></tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Paso 2: líneas de la OC elegida -->
                            <div id="oc_paso_lineas" class="d-none">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-bold" id="oc_lineas_titulo"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnOcVolver">
                                        <i class="fas fa-arrow-left me-1"></i>Otras órdenes
                                    </button>
                                </div>
                                <p class="small text-muted mb-2">
                                    "En otras facturas" es lo cargado en facturas todavía sin confirmar. Puede facturar más cantidad que la pendiente.
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center"><input type="checkbox" class="form-check-input" id="oc_check_todos" checked></th>
                                                <th>Producto</th>
                                                <th class="text-end">Pedido</th>
                                                <th class="text-end">Recibido</th>
                                                <th class="text-end">En otras fact.</th>
                                                <th class="text-end">Pendiente</th>
                                                <th style="width: 110px;">Cantidad</th>
                                                <th style="width: 120px;">Precio</th>
                                            </tr>
                                        </thead>
                                        <tbody id="oc_tabla_lineas"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm d-none" id="btnOcAgregarLineas">
                                <i class="fas fa-plus me-1"></i>Agregar a la factura
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal Importar Certificación de Avance / Contrato -->
            <div class="modal fade" id="modalImportarCertificacion" tabindex="-1" aria-labelledby="modalImportarCertLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header py-2 d-flex justify-content-between align-items-center bg-success bg-opacity-10">
                            <h5 class="modal-title mb-0" id="modalImportarCertLabel">
                                <i class="fas fa-award text-success me-2"></i>Importar Certificación de Avance (Contrato de Servicios / Obra)
                            </h5>
                            <button type="button" class="btn-close m-0 ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="alert alert-info py-2 px-3 small mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Listado de certificaciones de avance aprobadas y pendientes de facturar para este contratista. Al seleccionarla, se vinculará la orden de compra y se pre-cargará la línea por el <strong>Neto Facturable</strong> (descontando automáticamente amortizaciones de anticipo y fondo de reparo).
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Contrato / OC</th>
                                            <th>N° Cert.</th>
                                            <th>Período</th>
                                            <th>Fecha Medición</th>
                                            <th class="text-center">Avance Acum.</th>
                                            <th class="text-end">Bruto Período</th>
                                            <th class="text-end text-danger">Amort. Anticipo</th>
                                            <th class="text-end text-warning">Fondo Reparo</th>
                                            <th class="text-end fw-bold text-success">Neto Facturable</th>
                                            <th class="text-center" style="width: 140px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cert_tabla_disponibles"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <!-- Modal Importación ARCA (Comprobantes Recibidos) -->
            <div class="modal fade" id="modalImportarArca" tabindex="-1" aria-labelledby="modalImportarArcaLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title mb-0" id="modalImportarArcaLabel">
                                <i class="fas fa-file-invoice text-primary me-2"></i>Importar Comprobantes Recibidos de ARCA
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <!-- Instrucciones y Selector de Archivo -->
                            <div class="card card-outline card-primary mb-3">
                                <div class="card-body p-3">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="fas fa-file-excel text-success me-1"></i>Archivo exportado de ARCA (.csv, .xlsx, .xls)
                                            </label>
                                            <input type="file" class="form-control form-control-sm" id="inputArchivoArca" accept=".csv, .xlsx, .xls">
                                            <small class="text-muted d-block mt-1">Archivo descargado desde "Mis Comprobantes Recibidos" de ARCA (ex AFIP). Cada comprobante se imputa al producto de la última compra del proveedor o, si no tiene, a "Gastos varios".</small>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold mb-1">Sucursal Destino</label>
                                            <select class="form-select form-select-sm" id="arca_sucursal_id"></select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold mb-1">Depósito Destino</label>
                                            <select class="form-select form-select-sm" id="arca_deposito_id"></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold mb-1">Condición de Pago</label>
                                            <select class="form-select form-select-sm" id="arca_condicion_pago_id"></select>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check pt-2">
                                                <input class="form-check-input" type="checkbox" id="arca_crear_proveedores" checked>
                                                <label class="form-check-label small fw-bold" for="arca_crear_proveedores">
                                                    Dar de alta automáticamente proveedores que no existan
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnLimpiarArca">
                                                <i class="fas fa-trash me-1"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Resumen / Estadísticas -->
                            <div id="arca_resumen" class="d-none mb-3">
                                <div class="row g-2">
                                    <div class="col-md-2 col-6">
                                        <div class="border rounded p-2 text-center bg-light shadow-sm">
                                            <div class="small text-muted">Total Detectados</div>
                                            <div class="fs-5 fw-bold" id="cnt_arca_total">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="border rounded p-2 text-center bg-light shadow-sm">
                                            <div class="small text-success">Listos para Importar</div>
                                            <div class="fs-5 fw-bold text-success" id="cnt_arca_listos">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="border rounded p-2 text-center bg-light shadow-sm">
                                            <div class="small text-warning">Duplicados (se omiten)</div>
                                            <div class="fs-5 fw-bold text-warning" id="cnt_arca_duplicados">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="border rounded p-2 text-center bg-light shadow-sm">
                                            <div class="small text-primary">Proveedores Nuevos</div>
                                            <div class="fs-5 fw-bold text-primary" id="cnt_arca_nuevos_prov">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="border rounded p-2 text-center bg-light shadow-sm">
                                            <div class="small text-danger">Con Error (se omiten)</div>
                                            <div class="fs-5 fw-bold text-danger" id="cnt_arca_errores">0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <div class="border rounded p-2 text-center bg-light shadow-sm">
                                            <div class="small text-secondary">Listos con Avisos</div>
                                            <div class="fs-5 fw-bold text-secondary" id="cnt_arca_avisos">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Barra de progreso -->
                            <div id="arca_progreso_contenedor" class="progress mb-3 d-none" style="height: 22px;">
                                <div id="arca_progreso_barra" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;">0%</div>
                            </div>

                            <!-- Tabla de Previsualización -->
                            <div id="arca_tabla_contenedor" class="table-responsive d-none border rounded" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-bordered table-hover align-middle mb-0" id="tablaPreviewArca">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="40" class="text-center">#</th>
                                            <th width="120">Estado</th>
                                            <th width="90">Fecha</th>
                                            <th width="120">Tipo</th>
                                            <th width="130">Punto - Número</th>
                                            <th width="120">CUIT</th>
                                            <th>Proveedor</th>
                                            <th width="160">Imputación</th>
                                            <th width="100" class="text-end">Neto</th>
                                            <th width="90" class="text-end">IVA</th>
                                            <th width="100" class="text-end">No grav. / Otros</th>
                                            <th width="110" class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyPreviewArca"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnConfirmarImportarArca" disabled>
                                <i class="fas fa-cloud-upload-alt me-1"></i>Confirmar e Importar (<span id="txtCantAImportar">0</span>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Catálogo / Selector de Productos y Servicios -->
            <div class="modal fade" id="modalSelectorProductos" tabindex="-1" aria-labelledby="modalSelectorProductosLabel" aria-hidden="true" style="z-index: 1065;">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content shadow border-0">
                        <div class="modal-header py-2 bg-light border-bottom">
                            <h6 class="modal-title mb-0 text-dark fw-bold" id="modalSelectorProductosLabel">
                                <i class="fas fa-boxes text-warning me-2"></i>Catálogo de Productos y Servicios
                            </h6>
                            <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="row g-2 mb-3">
                                <div class="col-md-7">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                        <input type="text" class="form-control" id="filtroModalProductos" placeholder="Buscar por código, nombre o cuenta contable...">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <select class="form-select form-select-sm" id="filtroModalCategoria">
                                        <option value="">Todas las categorías</option>
                                    </select>
                                </div>
                            </div>
                            <div class="table-responsive border rounded" style="max-height: 380px;">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaModalProductos">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="120">Código</th>
                                            <th>Descripción / Producto</th>
                                            <th width="150">Categoría</th>
                                            <th width="140">Cta. Gasto / Compra</th>
                                            <th width="70" class="text-center">IVA</th>
                                            <th width="80" class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyModalProductos">
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-1"></i> Cargando catálogo...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer py-2 bg-light border-top d-flex justify-content-between">
                            <small class="text-muted" id="txtTotalProductosModal">0 productos encontrados</small>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
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
        /* Modal de la factura: casi pantalla completa (antes la regla era genérica y lo limitaba a 1400px) */
        #modalFacturaProveedor .modal-factura-proveedor {
            max-width: 98vw;
            width: 98vw;
            margin: 1vh auto;
        }

        #modalFacturaProveedor .modal-content {
            min-height: 96vh;
        }

        #modalFacturaProveedor .modal-body {
            max-height: calc(98vh - 70px);
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
        /* Carga desde archivo: documento a la izquierda, formulario a la derecha */
        .fl-split { display: flex; gap: 1rem; align-items: flex-start; }
        .fl-form { flex: 1 1 auto; min-width: 0; }
        .fl-panel { flex: 0 0 38%; max-width: 38%; position: sticky; top: 0; display: flex; flex-direction: column;
                    gap: .5rem; max-height: calc(96vh - 90px); }
        .fl-panel-barra { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .fl-visor { flex: 1 1 55%; min-height: 260px; border: 1px solid #dee2e6; border-radius: .375rem; background: #f1f3f5; overflow: auto; }
        .fl-visor iframe, .fl-visor img { width: 100%; display: block; border: 0; }
        .fl-visor iframe { height: 100%; min-height: 420px; }
        .fl-resumen { flex: 1 1 45%; overflow: auto; font-size: .85rem; }
        .fl-resumen table td { padding: .15rem .35rem; vertical-align: top; }
        .fl-capa { font-size: .72rem; }
        .fl-conf-alta { color: #146c43; }
        .fl-conf-media { color: #997404; }
        .fl-conf-baja { color: #b02a37; }
        .fl-campo-media { background-color: #fff8e1 !important; border-color: #f0c36d !important; }
        .fl-campo-baja { background-color: #fdecea !important; border-color: #e8a0a6 !important; }
        .fl-campo-error { border-color: #dc3545 !important; box-shadow: 0 0 0 .15rem rgba(220, 53, 69, .2) !important; }
        .fl-motivo { color: #b02a37; font-size: .75rem; margin-top: .15rem; }
        .fl-zona { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
                   gap: .15rem; padding: 2rem 1rem; border: 2px dashed #9ec5fe; border-radius: .5rem; cursor: pointer; background: #f8fbff; }
        .fl-zona.fl-arrastrando { background: #e7f1ff; border-color: #0d6efd; }
        .fl-progreso { margin-top: 1rem; }
        .fl-lote-tabla { max-height: 55vh; overflow-y: auto; font-size: .85rem; }
        .fl-lote-tabla .fl-motivo { max-width: 22rem; }
        @media (max-width: 991.98px) {
            .fl-split { flex-direction: column; }
            .fl-panel { flex-basis: auto; max-width: 100%; width: 100%; position: static; max-height: none; }
            .fl-visor { max-height: 50vh; }
        }
        <?php if (!empty($_GET['embed'])): ?>
        /* Modo embebido dentro de Bandeja de Documentos: muestra únicamente el formulario/visor de la factura */
        .app-header, .app-sidebar, .app-footer, .app-content-header, .content-wrapper { display: none !important; }
        .app-main, .app-content, .app-content > .container-fluid { margin: 0 !important; padding: 0 !important; background: #fff !important; }
        #modalFacturaProveedor .modal-factura-proveedor { max-width: 100vw !important; width: 100vw !important; height: 100vh !important; margin: 0 !important; }
        #modalFacturaProveedor .modal-content { min-height: 100vh !important; height: 100vh !important; border: 0 !important; border-radius: 0 !important; }
        #modalFacturaProveedor .modal-body { max-height: calc(100vh - 52px) !important; height: calc(100vh - 52px) !important; overflow-y: auto !important; }
        #btnToggleFullscreen { display: none !important; }
        <?php endif; ?>
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
    <script src="<?= asset_local('js/xlsx-0.18.5/xlsx.full.min.js') ?>"></script>
    <script src="<?= asset_local('js/arca-mis-comprobantes.js') ?>"></script>
    
    <script>
        const empresa_id = <?php echo json_encode($empresa_id); ?>;
        const pagina_idx = <?php echo json_encode($pagina_idx); ?>;
        const modulo_id = <?php echo json_encode($modulo_id); ?>;
        window.FP_CSRF = <?= json_encode(csrf_token()) ?>;
        window.FP_EMBED = <?= !empty($_GET['embed']) ? 'true' : 'false' ?>;
    </script>
    <?php // Compra por presentación (doc 27): selector de presentación en la línea, solo con el indicador
    require_once ROOT_PATH . '/core/compras_presentaciones.php';
    if (cpre_activo($conexion, (int) $current_empresa_id, 'factura')): ?>
    <script src="<?= asset_local('js/compras-presentaciones.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/compras-presentaciones.js') ?>"></script>
    <?php endif; ?>
    <script src="facturas_proveedores.js?v=<?= filemtime(__DIR__.'/facturas_proveedores.js') ?>"></script>
    <?php if ($puede_cargar_archivo): ?>
    <script src="<?= asset_local('js/facturas-proveedores-lectura.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/facturas-proveedores-lectura.js') ?>"></script>
    <?php endif; ?>
    


</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>