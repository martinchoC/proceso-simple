<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Ajustes de Costos de Productos";
$currentPage = 'productos_costos_ajustes';
$modudo_idx = 2;
$pagina_idx = 82; // ID de página para Ajustes de Costos

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Ajustes de Costos de Productos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Costos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ajustes de Costos</li>
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
                                        <table id="tablaAjustes" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="60">ID</th>
                                                    <th width="100">Tipo</th>
                                                    <th width="200">Descripción</th>
                                                    <th width="100">Valor Ajuste</th>
                                                    <th width="100">Tipo Valor</th>
                                                    <th width="110">Fecha Informado</th>
                                                    <th width="110">Vigencia Desde</th>
                                                    <th width="110">Vigencia Hasta</th>
                                                    <th width="100">Requiere Aprob.</th>
                                                    <th width="100">Estado</th>
                                                    <th width="150" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar Ajuste de Costo -->
            <div class="modal fade" id="modalAjuste" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Ajuste de Costo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formAjuste" class="needs-validation" novalidate>
                                <input type="hidden" id="producto_costo_ajuste_id" name="producto_costo_ajuste_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="ajuste_descripcion" class="form-label">Descripción *</label>
                                        <input type="text" class="form-control" id="ajuste_descripcion" 
                                            name="ajuste_descripcion" maxlength="255" required>
                                        <div class="invalid-feedback">La descripción es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="producto_costo_ajuste_tipo_id" class="form-label">Tipo de Ajuste *</label>
                                        <select class="form-select" id="producto_costo_ajuste_tipo_id" name="producto_costo_ajuste_tipo_id" required>
                                            <option value="">Seleccione un tipo...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de ajuste</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="producto_costo_ajuste_valor_tipo_id" class="form-label">Tipo de Valor</label>
                                        <select class="form-select" id="producto_costo_ajuste_valor_tipo_id" name="producto_costo_ajuste_valor_tipo_id">
                                            <option value="">Seleccione un tipo...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="valor_ajuste" class="form-label">Valor Ajuste</label>
                                        <input type="number" step="0.000001" class="form-control" id="valor_ajuste" 
                                            name="valor_ajuste">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="entidad_id" class="form-label">Entidad</label>
                                        <select class="form-select" id="entidad_id" name="entidad_id">
                                            <option value="">Seleccione una entidad...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="producto_id" class="form-label">Producto</label>
                                        <select class="form-select" id="producto_id" name="producto_id">
                                            <option value="">Seleccione un producto...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="proveedor_lista_costo_id" class="form-label">Lista de Costo Proveedor</label>
                                        <select class="form-select" id="proveedor_lista_costo_id" name="proveedor_lista_costo_id">
                                            <option value="">Seleccione una lista...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="f_informado" class="form-label">Fecha Informado *</label>
                                        <input type="date" class="form-control" id="f_informado" name="f_informado" required>
                                        <div class="invalid-feedback">La fecha informado es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="f_vigencia_desde" class="form-label">Vigencia Desde *</label>
                                        <input type="date" class="form-control" id="f_vigencia_desde" name="f_vigencia_desde" required>
                                        <div class="invalid-feedback">La fecha de vigencia desde es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="f_vigencia_hasta" class="form-label">Vigencia Hasta</label>
                                        <input type="date" class="form-control" id="f_vigencia_hasta" name="f_vigencia_hasta">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Configuración</label>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                    id="requiere_aprobacion" name="requiere_aprobacion" value="1" checked>
                                                <label class="form-check-label" for="requiere_aprobacion">
                                                    <i class="fas fa-check-circle text-primary me-1"></i>Requiere Aprobación
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="observaciones" class="form-label">Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" maxlength="65535"></textarea>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <!-- Sección de Detalles -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header bg-info text-white">
                                                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Detalles del Ajuste</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <button type="button" class="btn btn-sm btn-success" id="btnAgregarDetalle">
                                                        <i class="fas fa-plus me-1"></i>Agregar Producto
                                                    </button>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered" id="tablaDetalles">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th width="50">#</th>
                                                                <th>Producto ID</th>
                                                                <th>Producto</th>
                                                                <th width="150">Costo Anterior</th>
                                                                <th width="150">Valor Ajuste</th>
                                                                <th width="150">Costo Nuevo</th>
                                                                <th width="200">Observaciones</th>
                                                                <th width="80">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para seleccionar producto -->
            <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalProductoLabel">Seleccionar Producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="buscarProducto" placeholder="Buscar producto...">
                            </div>
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-sm table-bordered" id="tablaProductosBusqueda">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Precio</th>
                                            <th width="50">Seleccionar</th>
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
    </div>

    <style>
        .dt-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .dataTables_wrapper .dt-buttons {
            float: right;
            margin-top: 5px;
        }
        .badge-tipo {
            font-size: 0.85em;
            padding: 4px 8px;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        #tablaDetalles tbody tr {
            cursor: pointer;
        }
        .table-info {
            background-color: #cfe2ff !important;
        }
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto
            const empresa_idx = <?php echo isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2; ?>;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables para mantener el estado del DataTable
            var tabla;
            var currentPage = 0;
            var currentOrder = [[0, 'asc']];
            var currentSearch = '';
            
            // Array para almacenar detalles temporales
            var detalles = [];
            var nextDetalleId = 1;
            var productoSeleccionado = null;
            
            function cargarTiposAjuste() {
                $.ajax({
                    url: 'productos_costos_ajustes_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_tipos_ajuste' },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#producto_costo_ajuste_tipo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un tipo...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, tipo) {
                                select.append('<option value="' + tipo.producto_costo_ajuste_tipo_id + '">' + 
                                    escapeHtml(tipo.producto_costo_ajuste_tipo_nombre) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar tipos de ajuste:', error);
                    }
                });
            }

            function cargarTiposValor() {
                $.ajax({
                    url: 'productos_costos_ajustes_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_tipos_valor' },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#producto_costo_ajuste_valor_tipo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un tipo...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, tipo) {
                                select.append('<option value="' + tipo.producto_costo_ajuste_valor_tipo_id + '">' + 
                                    escapeHtml(tipo.producto_costo_ajuste_valor_tipo_nombre) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar tipos de valor:', error);
                    }
                });
            }

            function cargarEntidades() {
                $.ajax({
                    url: 'productos_costos_ajustes_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_entidades', empresa_idx: empresa_idx },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#entidad_id');
                        select.empty();
                        select.append('<option value="">Seleccione una entidad...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, entidad) {
                                select.append('<option value="' + entidad.entidad_id + '">' + 
                                    escapeHtml(entidad.entidad_nombre) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar entidades:', error);
                    }
                });
            }

            function cargarListasCostoProveedor() {
                $.ajax({
                    url: 'productos_costos_ajustes_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_listas_costo_proveedor', empresa_idx: empresa_idx },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#proveedor_lista_costo_id');
                        select.empty();
                        select.append('<option value="">Seleccione una lista...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, lista) {
                                select.append('<option value="' + lista.proveedor_lista_costo_id + '">' + 
                                    escapeHtml(lista.lista_nombre) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar listas de costo:', error);
                    }
                });
            }

            function buscarProductos(busqueda) {
                $.ajax({
                    url: 'productos_costos_ajustes_ajax.php',
                    type: 'GET',
                    data: { accion: 'buscar_productos', empresa_idx: empresa_idx, busqueda: busqueda },
                    dataType: 'json',
                    success: function(response) {
                        var tbody = $('#tablaProductosBusqueda tbody');
                        tbody.empty();
                        if (response && response.length > 0) {
                            $.each(response, function(index, producto) {
                                var row = `<tr>
                                    <td>${producto.producto_id}</td>
                                    <td>${escapeHtml(producto.producto_codigo || '')}</td>
                                    <td>${escapeHtml(producto.producto_nombre)}</td>
                                    <td class="text-end">${parseFloat(producto.producto_precio || 0).toFixed(2)}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary btn-seleccionar-producto" 
                                            data-id="${producto.producto_id}"
                                            data-codigo="${escapeHtml(producto.producto_codigo || '')}"
                                            data-nombre="${escapeHtml(producto.producto_nombre)}"
                                            data-precio="${producto.producto_precio || 0}">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </td>
                                </tr>`;
                                tbody.append(row);
                            });
                        } else {
                            tbody.html('<tr><td colspan="5" class="text-center">No se encontraron productos</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al buscar productos:', error);
                    }
                });
            }

            function agregarDetalle(producto) {
                var nuevoDetalle = {
                    temp_id: nextDetalleId++,
                    producto_id: producto.id,
                    producto_codigo: producto.codigo,
                    producto_nombre: producto.nombre,
                    costo_anterior: producto.precio,
                    valor_ajuste: 0,
                    costo_nuevo: producto.precio,
                    observaciones: ''
                };
                detalles.push(nuevoDetalle);
                renderizarDetalles();
            }

            function renderizarDetalles() {
                var tbody = $('#tablaDetalles tbody');
                tbody.empty();
                
                $.each(detalles, function(index, detalle) {
                    var row = `<tr data-id="${detalle.temp_id}">
                        <td>${index + 1}</td>
                        <td>${detalle.producto_id}</td>
                        <td>${escapeHtml(detalle.producto_codigo)} - ${escapeHtml(detalle.producto_nombre)}</td>
                        <td>
                            <input type="number" step="0.000001" class="form-control form-control-sm costo-anterior" 
                                value="${detalle.costo_anterior}" readonly>
                        </td>
                        <td>
                            <input type="number" step="0.000001" class="form-control form-control-sm valor-ajuste" 
                                value="${detalle.valor_ajuste}" data-id="${detalle.temp_id}">
                        </td>
                        <td>
                            <input type="number" step="0.000001" class="form-control form-control-sm costo-nuevo" 
                                value="${detalle.costo_nuevo}" readonly>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm detalle-observacion" 
                                value="${escapeHtml(detalle.observaciones)}" data-id="${detalle.temp_id}">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" data-id="${detalle.temp_id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                    tbody.append(row);
                });
                
                // Actualizar eventos de inputs
                $('.valor-ajuste').off('change').on('change', function() {
                    var tempId = $(this).data('id');
                    var valor = parseFloat($(this).val()) || 0;
                    var detalle = detalles.find(d => d.temp_id == tempId);
                    if (detalle) {
                        detalle.valor_ajuste = valor;
                        detalle.costo_nuevo = detalle.costo_anterior + valor;
                        $(this).closest('tr').find('.costo-nuevo').val(detalle.costo_nuevo.toFixed(6));
                    }
                });
                
                $('.detalle-observacion').off('change').on('change', function() {
                    var tempId = $(this).data('id');
                    var observacion = $(this).val();
                    var detalle = detalles.find(d => d.temp_id == tempId);
                    if (detalle) {
                        detalle.observaciones = observacion;
                    }
                });
            }

            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaAjustes')) {
                    $('#tablaAjustes').DataTable().destroy();
                    $('#tablaAjustes tbody').empty();
                }

                tabla = $('#tablaAjustes').DataTable({
                    ajax: {
                        url: 'productos_costos_ajustes_ajax.php',
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
                            if (searchValue === '-1' || searchValue === '' || searchValue === '-1') {
                                currentSearch = '';
                            } else {
                                currentSearch = searchValue;
                            }
                        } else {
                            currentSearch = '';
                        }
                        data.search = { search: currentSearch };
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                         '<"clear">',
                    pageLength: 50,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    columns: [
                        { data: 'producto_costo_ajuste_id', className: 'text-center fw-bold' },
                        { data: 'producto_costo_ajuste_tipo_nombre', render: function(data) { return data ? `<span class="badge bg-info">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>'; } },
                        { data: 'ajuste_descripcion', render: function(data) { return `<div class="fw-medium">${escapeHtml(data)}</div>`; } },
                        { data: 'valor_ajuste', className: 'text-end', render: function(data) { return data ? parseFloat(data).toFixed(6) : '<span class="text-muted">-</span>'; } },
                        { data: 'producto_costo_ajuste_valor_tipo_nombre', render: function(data) { return data ? escapeHtml(data) : '<span class="text-muted">-</span>'; } },
                        { data: 'f_informado', render: function(data) { return data ? data : '<span class="text-muted">-</span>'; } },
                        { data: 'f_vigencia_desde', render: function(data) { return data ? data : '<span class="text-muted">-</span>'; } },
                        { data: 'f_vigencia_hasta', render: function(data) { return data ? data : '<span class="text-muted">-</span>'; } },
                        { data: 'requiere_aprobacion', className: 'text-center', render: function(data) { return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg"></i>' : '<i class="fas fa-times-circle text-danger fa-lg"></i>'; } },
                        { data: 'estado_info', className: 'text-center', render: function(data) { 
                            if (!data || !data.estado_registro) return '<span class="fw-medium">Sin estado</span>';
                            var badgeClass = data.bg_clase ? data.bg_clase.replace('bg-', '') : 'secondary';
                            return `<span class="badge bg-${badgeClass}">${data.estado_registro}</span>`;
                        } },
                        { data: 'botones', orderable: false, searchable: false, className: "text-center", width: '150px', render: function(data, type, row) {
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
                                    var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" title="${titulo}" data-id="${row.producto_costo_ajuste_id}" data-accion="${accionJs}" data-confirmable="${esConfirmable}" data-tipo="${escapeHtml(row.ajuste_descripcion)}">${icono}</button>`;
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
                        } }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    order: currentOrder,
                    responsive: true,
                    createdRow: function (row, data, dataIndex) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                            $(row).addClass('table-secondary');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                            $(row).addClass('table-warning');
                        }
                    },
                    initComplete: function () {
                        $(tabla.table().container()).on('page.dt', function (e) { currentPage = tabla.page(); });
                        $(tabla.table().container()).on('order.dt', function (e, settings, details) { currentOrder = tabla.order(); });
                        $(tabla.table().container()).on('search.dt', function (e, settings) { currentSearch = tabla.search(); });
                        setTimeout(function () {
                            var searchInput = $('.dataTables_filter input');
                            if (searchInput.val() === '-1' || searchInput.val() === '') {
                                searchInput.val('');
                                currentSearch = '';
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
                    var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };
                    tabla.ajax.reload(function () {
                        if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                        if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                        btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                    }, false);
                });
            }

            function cargarBotonAgregar() {
                $.get('productos_costos_ajustes_ajax.php', { accion: 'obtener_boton_agregar', pagina_idx: pagina_idx }, function (botonAgregar) {
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                        var colorClase = 'btn-primary';
                        if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                            colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                        } else if (botonAgregar.color_clase) {
                            colorClase = botonAgregar.color_clase;
                        }
                        $('#contenedor-boton-agregar').html(`<button type="button" class="btn ${colorClase}" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`);
                    } else {
                        $('#contenedor-boton-agregar').html('<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Ajuste</button>');
                    }
                }, 'json');
            }

            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Ajuste de Costo');
                $('#f_informado').val(new Date().toISOString().split('T')[0]);
                $('#f_vigencia_desde').val(new Date().toISOString().split('T')[0]);
                $('#requiere_aprobacion').prop('checked', true);
                detalles = [];
                nextDetalleId = 1;
                renderizarDetalles();
                var modal = new bootstrap.Modal(document.getElementById('modalAjuste'));
                modal.show();
                $('#ajuste_descripcion').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var nombre = $(this).data('tipo');
                if (accionJs === 'editar') {
                    cargarAjusteParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el ajuste <strong>"${nombre}"</strong>?`,
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
                            ejecutarAccion(id, accionJs, nombre);
                        }
                    });
                } else {
                    ejecutarAccion(id, accionJs, nombre);
                }
            });

            function ejecutarAccion(id, accionJs, nombre) {
                var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };
                $.post('productos_costos_ajustes_ajax.php', {
                    accion: 'ejecutar_accion',
                    producto_costo_ajuste_id: id,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload(function () {
                            if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                            if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                            Swal.fire({ icon: "success", title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`, text: res.message || `Ajuste "${nombre}" actualizado correctamente`, showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                        }, false);
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: res.error || `Error al ${accionJs} el ajuste`, confirmButtonText: "Entendido" });
                    }
                }, 'json');
            }

            function cargarAjusteParaEditar(id) {
                $.get('productos_costos_ajustes_ajax.php', {
                    accion: 'obtener',
                    producto_costo_ajuste_id: id,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.producto_costo_ajuste_id) {
                        resetModal();
                        $('#producto_costo_ajuste_id').val(res.producto_costo_ajuste_id);
                        $('#ajuste_descripcion').val(res.ajuste_descripcion);
                        $('#producto_costo_ajuste_tipo_id').val(res.producto_costo_ajuste_tipo_id);
                        $('#producto_costo_ajuste_valor_tipo_id').val(res.producto_costo_ajuste_valor_tipo_id || '');
                        $('#valor_ajuste').val(res.valor_ajuste);
                        $('#entidad_id').val(res.entidad_id || '');
                        $('#producto_id').val(res.producto_id || '');
                        $('#proveedor_lista_costo_id').val(res.proveedor_lista_costo_id || '');
                        $('#f_informado').val(res.f_informado);
                        $('#f_vigencia_desde').val(res.f_vigencia_desde);
                        $('#f_vigencia_hasta').val(res.f_vigencia_hasta || '');
                        $('#requiere_aprobacion').prop('checked', res.requiere_aprobacion == 1);
                        $('#observaciones').val(res.observaciones || '');
                        
                        // Cargar detalles
                        if (res.detalles && res.detalles.length > 0) {
                            detalles = [];
                            nextDetalleId = 1;
                            $.each(res.detalles, function(index, detalle) {
                                detalles.push({
                                    temp_id: nextDetalleId++,
                                    producto_costo_ajuste_detalle_id: detalle.producto_costo_ajuste_detalle_id,
                                    producto_id: detalle.producto_id,
                                    producto_codigo: detalle.producto_codigo,
                                    producto_nombre: detalle.producto_nombre,
                                    costo_anterior: parseFloat(detalle.costo_anterior || 0),
                                    valor_ajuste: parseFloat(detalle.valor_ajuste || 0),
                                    costo_nuevo: parseFloat(detalle.costo_nuevo || 0),
                                    observaciones: detalle.observaciones || ''
                                });
                            });
                            renderizarDetalles();
                        }
                        
                        $('#modalLabel').text('Editar Ajuste de Costo');
                        var modal = new bootstrap.Modal(document.getElementById('modalAjuste'));
                        modal.show();
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos del ajuste", confirmButtonText: "Entendido" });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formAjuste')[0].reset();
                $('#producto_costo_ajuste_id').val('');
                $('#formAjuste').removeClass('was-validated');
                $('#producto_costo_ajuste_valor_tipo_id').val('');
                $('#entidad_id').val('');
                $('#producto_id').val('');
                $('#proveedor_lista_costo_id').val('');
                $('#valor_ajuste').val('');
                $('#f_vigencia_hasta').val('');
                $('#observaciones').val('');
                $('#requiere_aprobacion').prop('checked', true);
            }

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formAjuste');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#producto_costo_ajuste_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                
                var descripcion = $('#ajuste_descripcion').val().trim();
                if (!descripcion) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "La descripción es obligatoria", confirmButtonText: "Entendido" });
                    return false;
                }

                var tipoId = $('#producto_costo_ajuste_tipo_id').val();
                if (!tipoId) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un tipo de ajuste", confirmButtonText: "Entendido" });
                    return false;
                }

                var fInformado = $('#f_informado').val();
                if (!fInformado) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "La fecha informado es obligatoria", confirmButtonText: "Entendido" });
                    return false;
                }

                var fVigenciaDesde = $('#f_vigencia_desde').val();
                if (!fVigenciaDesde) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "La fecha de vigencia desde es obligatoria", confirmButtonText: "Entendido" });
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };

                $.ajax({
                    url: 'productos_costos_ajustes_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        producto_costo_ajuste_id: id,
                        ajuste_descripcion: descripcion,
                        producto_costo_ajuste_tipo_id: tipoId,
                        producto_costo_ajuste_valor_tipo_id: $('#producto_costo_ajuste_valor_tipo_id').val(),
                        valor_ajuste: $('#valor_ajuste').val(),
                        entidad_id: $('#entidad_id').val(),
                        producto_id: $('#producto_id').val(),
                        proveedor_lista_costo_id: $('#proveedor_lista_costo_id').val(),
                        f_informado: fInformado,
                        f_vigencia_desde: fVigenciaDesde,
                        f_vigencia_hasta: $('#f_vigencia_hasta').val(),
                        requiere_aprobacion: $('#requiere_aprobacion').is(':checked') ? 1 : 0,
                        observaciones: $('#observaciones').val(),
                        detalles: JSON.stringify(detalles),
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.resultado) {
                            tabla.ajax.reload(function () {
                                if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                                if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                                btnGuardar.prop('disabled', false).html(originalText);
                                Swal.fire({ icon: "success", title: "¡Guardado!", text: "Ajuste guardado correctamente", showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                                var modalEl = document.getElementById('modalAjuste');
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                modal.hide();
                            }, false);
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar los datos", confirmButtonText: "Entendido" });
                        }
                    },
                    error: function(xhr, status, error) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        console.error('Error:', error);
                        Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor", confirmButtonText: "Entendido" });
                    }
                });
            });

            $('#btnAgregarDetalle').click(function() {
                $('#modalProducto').modal('show');
                buscarProductos('');
            });

            $('#buscarProducto').on('input', function() {
                buscarProductos($(this).val());
            });

            $(document).on('click', '.btn-seleccionar-producto', function() {
                var producto = {
                    id: $(this).data('id'),
                    codigo: $(this).data('codigo'),
                    nombre: $(this).data('nombre'),
                    precio: $(this).data('precio')
                };
                agregarDetalle(producto);
                $('#modalProducto').modal('hide');
                $('#buscarProducto').val('');
            });

            $(document).on('click', '.btn-eliminar-detalle', function() {
                var tempId = $(this).data('id');
                detalles = detalles.filter(d => d.temp_id != tempId);
                renderizarDetalles();
            });

            $('#btnExportarExcel').click(function (e) { e.preventDefault(); exportToExcel(); });
            $('#btnExportarPDF').click(function (e) { e.preventDefault(); exportToPDF(); });
            $('#btnExportarCSV').click(function (e) { e.preventDefault(); exportToCSV(); });
            $('#btnExportarPrint').click(function (e) { e.preventDefault(); exportToPrint(); });

            function exportToExcel() {
                var data = tabla.rows().data().toArray();
                var exportData = data.map(row => ({
                    'ID': row.producto_costo_ajuste_id,
                    'Tipo': row.producto_costo_ajuste_tipo_nombre || '',
                    'Descripción': row.ajuste_descripcion,
                    'Valor Ajuste': row.valor_ajuste || '',
                    'Tipo Valor': row.producto_costo_ajuste_valor_tipo_nombre || '',
                    'Fecha Informado': row.f_informado || '',
                    'Vigencia Desde': row.f_vigencia_desde || '',
                    'Vigencia Hasta': row.f_vigencia_hasta || '',
                    'Requiere Aprobación': row.requiere_aprobacion == 1 ? 'Sí' : 'No',
                    'Observaciones': row.observaciones || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'AjustesCostos');
                XLSX.writeFile(wb, `AjustesCostos_${new Date().toISOString().slice(0,19)}.xlsx`);
            }

            function exportToPDF() {
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Ajustes de Costos</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body><h2>Ajustes de Costos de Productos</h2><table><thead><tr><th>ID</th><th>Tipo</th><th>Descripción</th><th>Valor</th><th>Vigencia Desde</th><th>Estado</th></tr></thead><tbody>';
                var data = tabla.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr><td>${row.producto_costo_ajuste_id}</td><td>${escapeHtml(row.producto_costo_ajuste_tipo_nombre || '')}</td><td>${escapeHtml(row.ajuste_descripcion)}</td><td>${row.valor_ajuste || ''}</td><td>${row.f_vigencia_desde || ''}</td><td>${row.estado_info?.estado_registro || ''}</td></tr>`;
                });
                content += '</tbody></table></body></html>';
                printWindow.document.write(content);
                printWindow.document.close();
                printWindow.print();
            }

            function exportToCSV() {
                var data = tabla.rows().data().toArray();
                var csv = "ID,Tipo,Descripción,Valor Ajuste,Fecha Informado,Vigencia Desde,Vigencia Hasta,Requiere Aprobación,Estado\n";
                data.forEach(row => {
                    csv += `"${row.producto_costo_ajuste_id}","${escapeCsv(row.producto_costo_ajuste_tipo_nombre || '')}","${escapeCsv(row.ajuste_descripcion)}","${row.valor_ajuste || ''}","${row.f_informado || ''}","${row.f_vigencia_desde || ''}","${row.f_vigencia_hasta || ''}","${row.requiere_aprobacion == 1 ? 'Sí' : 'No'}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `AjustesCostos_${new Date().toISOString().slice(0,19)}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function exportToPrint() { window.print(); }
            function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, function(m) { if (m === '&') return '&amp;'; if (m === '<') return '&lt;'; if (m === '>') return '&gt;'; return m; }); }
            function escapeCsv(str) { if (!str) return ''; return str.replace(/"/g, '""'); }

            inicializarDataTable();
            cargarBotonAgregar();
            cargarTiposAjuste();
            cargarTiposValor();
            cargarEntidades();
            cargarListasCostoProveedor();
            
            $('[title]').tooltip({ trigger: 'hover', placement: 'top' });
        });
    </script>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>